<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\LotInventory;
use App\Models\Movement;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\KardexService;

/**
 * Unit-style coverage for KardexService — the FIFO/FEFO ledger.
 *
 * These run inside TenantTestCase (so each test gets a fresh tenant DB),
 * but exercise the service directly without going through HTTP.
 */
function makeKardexScaffold(bool $tracked = false): array
{
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->for($company)->create();
    $category = Category::factory()->create();
    $uom = UnitOfMeasure::factory()->unit()->create();
    $template = ProductTemplate::factory()
        ->when($tracked, fn ($f) => $f->trackedByLot())
        ->create([
            'category_id' => $category->id,
            'uom_id' => $uom->id,
        ]);
    $variant = ProductProduct::factory()->principal()->create([
        'product_template_id' => $template->id,
        'price' => 100,
        'cost_price' => 50,
    ]);

    return compact('company', 'warehouse', 'variant');
}

function makeShellMovement(int $warehouseId, int $companyId, string $type = 'entry'): Movement
{
    return Movement::factory()->state(['type' => $type])->posted()->create([
        'warehouse_id' => $warehouseId,
        'company_id' => $companyId,
    ]);
}

it('registers an entry and returns it as the current stock', function (): void {
    $scaffold = makeKardexScaffold();
    $kardex = app(KardexService::class);

    $movement = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);

    $kardex->registerEntry(
        $movement,
        ['id' => $scaffold['variant']->id, 'quantity' => 25, 'price' => 10, 'subtotal' => 250],
        $scaffold['warehouse']->id,
        'seed',
    );

    $stock = $kardex->getCurrentStock($scaffold['variant']->id, $scaffold['warehouse']->id);
    expect($stock)->toBe(25.0);

    $last = $kardex->getLastRecord($scaffold['variant']->id, $scaffold['warehouse']->id);
    expect((float) $last['cost'])->toBe(10.0);
});

it('keeps a moving-average cost across two entries at different prices', function (): void {
    $scaffold = makeKardexScaffold();
    $kardex = app(KardexService::class);

    $m1 = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $m1,
        ['id' => $scaffold['variant']->id, 'quantity' => 10, 'price' => 10, 'subtotal' => 100],
        $scaffold['warehouse']->id,
        'first',
    );

    $m2 = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $m2,
        ['id' => $scaffold['variant']->id, 'quantity' => 10, 'price' => 20, 'subtotal' => 200],
        $scaffold['warehouse']->id,
        'second',
    );

    $last = $kardex->getLastRecord($scaffold['variant']->id, $scaffold['warehouse']->id);
    // (10*10 + 10*20) / 20 = 15
    expect((float) $last['cost'])->toBe(15.0)
        ->and((float) $last['quantity'])->toBe(20.0);
});

it('throws when an explicit lot allocation overdraws lot inventory', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    $lot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'OD',
        'expires_at' => now()->addDays(30),
        'initial_quantity' => 5,
        'initial_cost' => 10,
        'status' => 'active',
    ]);

    $entry = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $entry,
        ['id' => $scaffold['variant']->id, 'quantity' => 5, 'price' => 10, 'subtotal' => 50],
        $scaffold['warehouse']->id,
        'seed',
        $lot->id,
    );

    $exit = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id, type: 'exit');

    expect(fn () => $kardex->registerExit(
        $exit,
        ['id' => $scaffold['variant']->id, 'quantity' => 10],
        $scaffold['warehouse']->id,
        'overdraft',
        [['lot_id' => $lot->id, 'quantity' => 10]],
    ))->toThrow(RuntimeException::class);
});

it('reports hasEnoughStock correctly', function (): void {
    $scaffold = makeKardexScaffold();
    $kardex = app(KardexService::class);

    $movement = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $movement,
        ['id' => $scaffold['variant']->id, 'quantity' => 8, 'price' => 5, 'subtotal' => 40],
        $scaffold['warehouse']->id,
        'seed',
    );

    expect($kardex->hasEnoughStock($scaffold['variant']->id, $scaffold['warehouse']->id, 5))->toBeTrue()
        ->and($kardex->hasEnoughStock($scaffold['variant']->id, $scaffold['warehouse']->id, 9))->toBeFalse();
});

it('allocates FEFO across two lots with different expiry dates', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    // Older expiry (should be picked first by FEFO).
    $earlyLot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'EARLY',
        'expires_at' => now()->addDays(10),
        'initial_quantity' => 5,
        'initial_cost' => 10,
        'status' => 'active',
    ]);
    $lateLot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'LATE',
        'expires_at' => now()->addDays(60),
        'initial_quantity' => 10,
        'initial_cost' => 12,
        'status' => 'active',
    ]);

    // Seed lot stock by registering entries pinned to each lot.
    $entryA = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $entryA,
        ['id' => $scaffold['variant']->id, 'quantity' => 5, 'price' => 10, 'subtotal' => 50],
        $scaffold['warehouse']->id,
        'seed-early',
        $earlyLot->id,
    );
    $entryB = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $entryB,
        ['id' => $scaffold['variant']->id, 'quantity' => 10, 'price' => 12, 'subtotal' => 120],
        $scaffold['warehouse']->id,
        'seed-late',
        $lateLot->id,
    );

    // Need 8: must take 5 from EARLY then 3 from LATE.
    $allocations = $kardex->allocateFefo($scaffold['variant']->id, $scaffold['warehouse']->id, 8);

    expect($allocations)->toHaveCount(2)
        ->and($allocations[0]['lot_id'])->toBe($earlyLot->id)
        ->and((float) $allocations[0]['quantity'])->toBe(5.0)
        ->and($allocations[1]['lot_id'])->toBe($lateLot->id)
        ->and((float) $allocations[1]['quantity'])->toBe(3.0);
});

it('decrements lot inventory when registering an exit pinned to a lot', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    $lot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'L1',
        'expires_at' => now()->addDays(30),
        'initial_quantity' => 20,
        'initial_cost' => 8,
        'status' => 'active',
    ]);

    $entry = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $entry,
        ['id' => $scaffold['variant']->id, 'quantity' => 20, 'price' => 8, 'subtotal' => 160],
        $scaffold['warehouse']->id,
        'seed',
        $lot->id,
    );

    $exit = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id, type: 'exit');
    $kardex->registerExit(
        $exit,
        ['id' => $scaffold['variant']->id, 'quantity' => 7],
        $scaffold['warehouse']->id,
        'sell',
        [['lot_id' => $lot->id, 'quantity' => 7]],
    );

    $li = LotInventory::where('lot_id', $lot->id)
        ->where('warehouse_id', $scaffold['warehouse']->id)
        ->first();

    expect((float) $li->quantity_balance)->toBe(13.0);
});

it('marks a lot as depleted once its inventory reaches zero', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    $lot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'DPL',
        'expires_at' => now()->addDays(30),
        'initial_quantity' => 3,
        'initial_cost' => 5,
        'status' => 'active',
    ]);

    $entry = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);
    $kardex->registerEntry(
        $entry,
        ['id' => $scaffold['variant']->id, 'quantity' => 3, 'price' => 5, 'subtotal' => 15],
        $scaffold['warehouse']->id,
        'seed',
        $lot->id,
    );

    $exit = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id, type: 'exit');
    $kardex->registerExit(
        $exit,
        ['id' => $scaffold['variant']->id, 'quantity' => 3],
        $scaffold['warehouse']->id,
        'sell-all',
        [['lot_id' => $lot->id, 'quantity' => 3]],
    );

    expect($lot->fresh()->status)->toBe('depleted');
});

it('rejects selling from a blocked lot', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    $lot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'BLOCK',
        'expires_at' => now()->addDays(30),
        'initial_quantity' => 5,
        'initial_cost' => 10,
        'status' => 'blocked',
    ]);

    expect(fn () => $kardex->assertLotSellable($lot->id))
        ->toThrow(RuntimeException::class);
});

it('rejects selling from an expired lot unless explicitly allowed', function (): void {
    $scaffold = makeKardexScaffold(tracked: true);
    $kardex = app(KardexService::class);

    $lot = Lot::factory()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'EXP',
        'expires_at' => now()->subDay(),
        'initial_quantity' => 5,
        'initial_cost' => 10,
        'status' => 'active',
    ]);

    expect(fn () => $kardex->assertLotSellable($lot->id))->toThrow(RuntimeException::class);
    // Allow override
    $kardex->assertLotSellable($lot->id, allowExpired: true);
    expect(true)->toBeTrue(); // reached without throwing
});

it('logs entry rows with the polymorphic relation set to the source model', function (): void {
    $scaffold = makeKardexScaffold();
    $kardex = app(KardexService::class);

    $movement = makeShellMovement($scaffold['warehouse']->id, $scaffold['company']->id);

    $kardex->registerEntry(
        $movement,
        ['id' => $scaffold['variant']->id, 'quantity' => 4, 'price' => 5, 'subtotal' => 20],
        $scaffold['warehouse']->id,
        'check-poly',
    );

    $row = Inventory::query()->latest('id')->first();
    expect($row->inventoryable_type)->toBe(Movement::class)
        ->and($row->inventoryable_id)->toBe($movement->id);
});
