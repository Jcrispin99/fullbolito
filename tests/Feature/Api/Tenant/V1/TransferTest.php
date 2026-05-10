<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Movement;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sequence;
use App\Models\Transfer;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\KardexService;

/**
 * Build the minimum scaffold for transfers between two warehouses:
 *  - one company with two warehouses (origin + destination)
 *  - the three required journals: transfer (header), movement_exit, movement_entry
 *  - one storable (non-lot-tracked) product
 *  - pre-stock the origin warehouse so exit can be posted
 */
function makeTransferScaffold(int $initialStock = 50): array
{
    $company = Company::factory()->create();
    $fromWarehouse = Warehouse::factory()->for($company)->create(['name' => 'Origin']);
    $toWarehouse = Warehouse::factory()->for($company)->create(['name' => 'Destination']);

    // Each journal gets its own sequence (otherwise the unique sequence_id constraint or
    // shared correlatives across header + movements would clash).
    $transferJournal = Journal::factory()->transfer()->create([
        'company_id' => $company->id,
        'code' => 'T001',
        'sequence_id' => Sequence::factory()->create(['prefix' => 'T001', 'sequence_size' => 8])->id,
    ]);
    $exitJournal = Journal::factory()->movementExit()->create([
        'company_id' => $company->id,
        'code' => 'SAL',
        'sequence_id' => Sequence::factory()->create(['prefix' => 'SAL', 'sequence_size' => 6])->id,
    ]);
    $entryJournal = Journal::factory()->movementEntry()->create([
        'company_id' => $company->id,
        'code' => 'ENT',
        'sequence_id' => Sequence::factory()->create(['prefix' => 'ENT', 'sequence_size' => 6])->id,
    ]);

    $category = Category::factory()->create();
    $uom = UnitOfMeasure::factory()->unit()->create();
    $template = ProductTemplate::factory()->create([
        'category_id' => $category->id,
        'uom_id' => $uom->id,
    ]);
    $variant = ProductProduct::factory()->principal()->create([
        'product_template_id' => $template->id,
        'price' => 100,
        'cost_price' => 50,
    ]);

    if ($initialStock > 0) {
        // Seed the origin warehouse with stock so exit transitions can post. Use the
        // KardexService's registerEntry directly with a synthetic Movement so
        // inventories() polymorphism works (we don't need the journal flow here).
        $seedMovement = Movement::factory()->entry()->posted()->create([
            'warehouse_id' => $fromWarehouse->id,
            'company_id' => $company->id,
            'reason' => 'test_seed',
        ]);

        app(KardexService::class)->registerEntry(
            $seedMovement,
            ['id' => $variant->id, 'quantity' => $initialStock, 'price' => 50, 'subtotal' => 50 * $initialStock],
            $fromWarehouse->id,
            'seed-stock',
        );
    }

    return compact('company', 'fromWarehouse', 'toWarehouse', 'transferJournal', 'exitJournal', 'entryJournal', 'variant');
}

function basicTransferPayload(array $scaffold, float $qty = 5): array
{
    return [
        'company_id' => $scaffold['company']->id,
        'from_warehouse_id' => $scaffold['fromWarehouse']->id,
        'to_warehouse_id' => $scaffold['toWarehouse']->id,
        'observation' => 'Test transfer',
        'products' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => $qty,
            ],
        ],
    ];
}

function validGrePayload(): array
{
    return [
        'gre_motive_code' => '04',
        'gre_modality' => 'private',
        'gre_transfer_start_date' => now()->toDateString(),
        'gre_gross_weight' => 12.5,
        'gre_packages' => 2,
        'gre_vehicle_plate' => 'ABC-123',
        'gre_driver_doc_type' => '1',
        'gre_driver_doc_number' => '12345678',
        'gre_driver_license' => 'Q12345678',
        'gre_driver_name' => 'Juan Pérez',
    ];
}

it('requires authentication to list transfers', function (): void {
    $this->tenantGetJson('/api/v1/transfers')->assertUnauthorized();
});

it('lists transfers for the authenticated user', function (): void {
    $this->actingAsTenantUser();
    makeTransferScaffold(initialStock: 0);

    $this->tenantGetJson('/api/v1/transfers')
        ->assertOk()
        ->assertJsonPath('success', true);
});

it('creates a draft transfer with computed total', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $response = $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold, qty: 4));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.from_warehouse_id', $scaffold['fromWarehouse']->id)
        ->assertJsonPath('data.to_warehouse_id', $scaffold['toWarehouse']->id);

    $transfer = Transfer::query()->latest('id')->first();
    expect($transfer->serie)->toBe('T001')
        ->and((float) $transfer->total)->toBe(200.0); // 4 * 50 cost
    expect($transfer->exitMovement)->not->toBeNull()
        ->and($transfer->entryMovement)->not->toBeNull()
        ->and($transfer->exitMovement->status)->toBe('draft')
        ->and($transfer->entryMovement->status)->toBe('draft');
});

it('rejects creating a transfer with same origin and destination warehouse', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $payload = basicTransferPayload($scaffold);
    $payload['to_warehouse_id'] = $payload['from_warehouse_id'];

    $this->tenantPostJson('/api/v1/transfers', $payload)
        ->assertStatus(422);
});

it('deletes a draft transfer and its paired movements', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $exitId = $transfer->exitMovement->id;
    $entryId = $transfer->entryMovement->id;

    $this->tenantDeleteJson("/api/v1/transfers/{$transfer->id}")->assertNoContent();

    expect(Transfer::find($transfer->id))->toBeNull()
        ->and(Movement::find($exitId))->toBeNull()
        ->and(Movement::find($entryId))->toBeNull();
});

it('sends a draft transfer (posts the exit movement)', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();

    $response = $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send");

    $response->assertOk()->assertJsonPath('data.status', 'in_transit');
    expect($transfer->fresh()->exitMovement->status)->toBe('posted');
});

it('receives an in-transit transfer (posts the entry movement)', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")->assertOk();

    $response = $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/receive");

    $response->assertOk()->assertJsonPath('data.status', 'completed');
    expect($transfer->fresh()->entryMovement->status)->toBe('posted');
});

it('cancels a sent transfer (writes contra-asiento on exit)', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")->assertOk();

    $response = $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($transfer->fresh()->exitMovement->status)->toBe('cancelled');
});

it('rejects sending a transfer when origin lacks stock', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold(initialStock: 0); // no stock

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold, qty: 5))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();

    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")
        ->assertStatus(422);
});

it('rejects the legacy draft restore endpoint', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();

    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/draft")->assertStatus(422);
});

it('persists GRE transport data on a draft transfer', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();

    $response = $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload());

    $response->assertOk()
        ->assertJsonPath('data.gre.motive_code', '04')
        ->assertJsonPath('data.gre.driver_name', 'Juan Pérez');

    expect($transfer->fresh()->gre_vehicle_plate)->toBe('ABC-123');
});

it('sends GRE to SUNAT (mocked) and stores ticket', function (): void {
    $fake = $this->mockGreenterDespatch();
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload())->assertOk();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")->assertOk();

    $response = $this->tenantPostJson("/api/v1/transfers/{$transfer->id}/gre/send");

    $response->assertOk()->assertJsonPath('data.gre.status', 'ticket_pending');
    expect($transfer->fresh()->gre_ticket)->not->toBeNull()
        ->and($fake->sentTransferIds)->toContain($transfer->id);
});

it('rejects sending GRE before exit movement is posted', function (): void {
    $this->mockGreenterDespatch();
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload())->assertOk();

    $this->tenantPostJson("/api/v1/transfers/{$transfer->id}/gre/send")->assertStatus(422);
});

it('polls a pending GRE ticket and persists accepted status', function (): void {
    $fake = $this->mockGreenterDespatch();
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload())->assertOk();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")->assertOk();
    $this->tenantPostJson("/api/v1/transfers/{$transfer->id}/gre/send")->assertOk();

    $response = $this->tenantPostJson("/api/v1/transfers/{$transfer->id}/gre/poll");

    $response->assertOk()->assertJsonPath('data.gre.status', 'accepted');
    expect($transfer->fresh()->gre_status)->toBe('accepted')
        ->and($fake->polledTransferIds)->toContain($transfer->id);
});

it('persists error status when GRE send fails (mocked)', function (): void {
    $this->mockGreenterDespatch(shouldSucceed: false);
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload())->assertOk();
    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/send")->assertOk();

    $this->tenantPostJson("/api/v1/transfers/{$transfer->id}/gre/send")->assertOk();

    expect($transfer->fresh()->gre_status)->toBe('error');
});

it('rejects GRE update once the despatch is accepted', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();
    $transfer->update(['gre_status' => 'accepted']);

    $this->tenantPatchJson("/api/v1/transfers/{$transfer->id}/gre", validGrePayload())
        ->assertStatus(422);
});

it('returns 404 when GRE XML download has no signed file', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeTransferScaffold();

    $this->tenantPostJson('/api/v1/transfers', basicTransferPayload($scaffold))->assertCreated();
    $transfer = Transfer::query()->latest('id')->first();

    $this->tenantGetJson("/api/v1/transfers/{$transfer->id}/gre/xml")->assertStatus(404);
});
