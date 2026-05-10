<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Purchase;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;

/**
 * Build the minimum scaffold for purchases: company + warehouse + supplier
 * + purchase journal (with sequence) + product variant (non-lot-tracked) + IGV tax.
 */
function makePurchaseScaffold(bool $tracked = false): array
{
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    $purchaseJournal = Journal::factory()->purchase()->create([
        'company_id' => $company->id,
        'code' => 'COMP',
        'sequence_id' => Sequence::factory()->create(['prefix' => 'F001'])->id,
    ]);

    // Tracked products need the LOT journal too (auto-generated lot numbers).
    if ($tracked) {
        Journal::factory()->create([
            'company_id' => $company->id,
            'type' => 'lot',
            'code' => 'LOT',
            'document_type_code' => null,
            'is_fiscal' => false,
            'sequence_id' => Sequence::factory()->create(['prefix' => 'LOT', 'sequence_size' => 6])->id,
        ]);
    }

    $tax = Tax::factory()->igv()->default()->create();

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

    $supplier = Partner::factory()->supplier()->create();

    return compact('company', 'warehouse', 'purchaseJournal', 'tax', 'variant', 'supplier');
}

function basicPurchasePayload(array $scaffold, ?array $overrides = null): array
{
    return array_merge([
        'partner_id' => $scaffold['supplier']->id,
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'vendor_bill_number' => 'F001-12345',
        'vendor_bill_date' => now()->toDateString(),
        'observation' => 'Test purchase',
        'products' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 10,
                'price' => 50,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
    ], $overrides ?? []);
}

it('requires authentication to list purchases', function (): void {
    $this->tenantGetJson('/api/v1/purchases')->assertUnauthorized();
});

it('lists purchases for the authenticated user', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    Purchase::factory()
        ->count(3)
        ->for($scaffold['warehouse'])
        ->for($scaffold['company'])
        ->for($scaffold['purchaseJournal'])
        ->for($scaffold['supplier'])
        ->create(['buyer_id' => $this->tenantUser->id]);

    $response = $this->tenantGetJson('/api/v1/purchases');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('creates a purchase as draft with computed total and IGV', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $response = $this->tenantPostJson('/api/v1/purchases', basicPurchasePayload($scaffold));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payment_status', 'unpaid');

    $purchase = Purchase::query()->latest('id')->first();
    // 10 * 50 = 500 subtotal, +18% IGV = 590 total
    expect((float) $purchase->total)->toBe(590.0)
        ->and($purchase->serie)->toBe('F001');
});

it('rejects deletion of a posted purchase', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $purchase = Purchase::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['purchaseJournal']->id,
        'partner_id' => $scaffold['supplier']->id,
        'buyer_id' => $this->tenantUser->id,
    ]);

    $this->tenantDeleteJson("/api/v1/purchases/{$purchase->id}")
        ->assertStatus(422);
    expect(Purchase::find($purchase->id))->not->toBeNull();
});

it('deletes a draft purchase', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $purchase = Purchase::factory()->draft()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['purchaseJournal']->id,
        'partner_id' => $scaffold['supplier']->id,
        'buyer_id' => $this->tenantUser->id,
    ]);

    $this->tenantDeleteJson("/api/v1/purchases/{$purchase->id}")->assertNoContent();
    expect(Purchase::find($purchase->id))->toBeNull();
});

it('posts a draft purchase and writes a kardex entry row', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $this->tenantPostJson('/api/v1/purchases', basicPurchasePayload($scaffold))->assertCreated();
    $purchase = Purchase::query()->latest('id')->first();

    $response = $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/post");

    $response->assertOk()->assertJsonPath('data.status', 'posted');

    $rows = Inventory::query()
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('warehouse_id', $scaffold['warehouse']->id)
        ->get();

    expect($rows)->toHaveCount(1)
        ->and((float) $rows->first()->quantity_in)->toBe(10.0)
        ->and((float) $rows->first()->quantity_balance)->toBe(10.0);
});

it('cancels a posted purchase and writes the contra-asiento (exit) row', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $this->tenantPostJson('/api/v1/purchases', basicPurchasePayload($scaffold))->assertCreated();
    $purchase = Purchase::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/post")->assertOk();

    $response = $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');

    $rows = Inventory::query()
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('warehouse_id', $scaffold['warehouse']->id)
        ->orderBy('id')
        ->get();

    // 1 entry row + 1 exit (contra-asiento) row, balance back to 0
    expect($rows)->toHaveCount(2)
        ->and((float) $rows->last()->quantity_out)->toBe(10.0)
        ->and((float) $rows->last()->quantity_balance)->toBe(0.0);
});

it('marks a posted unpaid purchase as paid', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $purchase = Purchase::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['purchaseJournal']->id,
        'partner_id' => $scaffold['supplier']->id,
        'buyer_id' => $this->tenantUser->id,
        'payment_status' => 'unpaid',
    ]);

    $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/pay")
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'paid');

    expect($purchase->fresh()->payment_status)->toBe('paid');
});

it('rejects pay on a draft purchase', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $purchase = Purchase::factory()->draft()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['purchaseJournal']->id,
        'partner_id' => $scaffold['supplier']->id,
        'buyer_id' => $this->tenantUser->id,
    ]);

    $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/pay")
        ->assertStatus(422);
});

it('restores a cancelled purchase to draft', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold();

    $purchase = Purchase::factory()->cancelled()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['purchaseJournal']->id,
        'partner_id' => $scaffold['supplier']->id,
        'buyer_id' => $this->tenantUser->id,
    ]);

    $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/draft")
        ->assertOk()
        ->assertJsonPath('data.status', 'draft');
});

it('creates a lot row when posting a purchase of a tracked product', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePurchaseScaffold(tracked: true);

    $payload = basicPurchasePayload($scaffold);
    // Provide a lot number for the line; otherwise PurchaseController autogenerates.
    $payload['products'][0]['lot_number'] = 'LOT-TEST-001';
    $payload['products'][0]['expires_at'] = now()->addYear()->toDateString();

    $this->tenantPostJson('/api/v1/purchases', $payload)->assertCreated();
    $purchase = Purchase::query()->latest('id')->first();

    $this->tenantPatchJson("/api/v1/purchases/{$purchase->id}/post")->assertOk();

    $lot = Lot::query()
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('lot_number', 'LOT-TEST-001')
        ->first();

    expect($lot)->not->toBeNull()
        ->and((float) $lot->initial_quantity)->toBe(10.0)
        ->and($lot->status)->toBe('active');
});
