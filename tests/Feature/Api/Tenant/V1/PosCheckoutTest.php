<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Journal;
use App\Models\PaymentMethod;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\PosSessionPayment;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sale;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;

/**
 * Build the minimum scaffold for a POS checkout:
 *  - company + warehouse pre-stocked
 *  - sale journal (boleta B001) attached to the POS config
 *  - PosConfig + opened PosSession
 *  - one product visible in POS, one cash payment method
 *  - IGV tax
 */
function makePosScaffold(): array
{
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    $sequence = Sequence::factory()->create(['prefix' => 'B001']);
    $journal = Journal::factory()->sale()->create([
        'company_id' => $company->id,
        'code' => 'B001',
        'sequence_id' => $sequence->id,
    ]);

    $tax = Tax::factory()->igv()->default()->create();

    $posConfig = PosConfig::factory()->create([
        'company_id' => $company->id,
        'warehouse_id' => $warehouse->id,
        'tax_id' => $tax->id,
        'apply_tax' => true,
        'prices_include_tax' => false,
        'is_active' => true,
    ]);
    $posConfig->journals()->attach($journal->id, ['document_type' => '03', 'is_default' => true]);

    $session = PosSession::factory()->opened()->create([
        'pos_config_id' => $posConfig->id,
    ]);

    $category = Category::factory()->create();
    $uom = UnitOfMeasure::factory()->unit()->create();
    $template = ProductTemplate::factory()->create([
        'category_id' => $category->id,
        'uom_id' => $uom->id,
        'is_active' => true,
        'is_pos_visible' => true,
    ]);
    $variant = ProductProduct::factory()->principal()->create([
        'product_template_id' => $template->id,
        'price' => 100,
        'cost_price' => 50,
    ]);

    $cash = PaymentMethod::factory()->cash()->create();

    return compact('company', 'warehouse', 'journal', 'tax', 'posConfig', 'session', 'variant', 'cash');
}

function basicPosCheckoutPayload(array $scaffold, ?array $overrides = null): array
{
    return array_merge([
        'pos_config_id' => $scaffold['posConfig']->id,
        'pos_session_id' => $scaffold['session']->id,
        'journal_id' => $scaffold['journal']->id,
        'items' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 2,
                'price' => 100,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $scaffold['cash']->id,
                'amount' => 236, // 200 net + 18% IGV
            ],
        ],
    ], $overrides ?? []);
}

it('creates a posted sale through POS checkout and returns change/paid amounts', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    $response = $this->tenantPostJson('/api/v1/pos/checkout', basicPosCheckoutPayload($scaffold));

    $response->assertCreated()
        ->assertJsonPath('data.sale.status', 'posted')
        ->assertJsonPath('data.sale.payment_status', 'paid')
        ->assertJsonPath('data.paid_amount', 236)
        ->assertJsonPath('data.change_amount', 0);

    $sale = Sale::query()->latest('id')->first();
    expect((float) $sale->subtotal)->toBe(200.0)
        ->and((float) $sale->tax_amount)->toBe(36.0)
        ->and((float) $sale->total)->toBe(236.0)
        ->and($sale->serie)->toBe('B001');
});

it('records a payment row tied to the POS session', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    $this->tenantPostJson('/api/v1/pos/checkout', basicPosCheckoutPayload($scaffold))->assertCreated();
    $sale = Sale::query()->latest('id')->first();

    $payment = PosSessionPayment::where('sale_id', $sale->id)->first();
    expect($payment)->not->toBeNull()
        ->and((int) $payment->pos_session_id)->toBe($scaffold['session']->id)
        ->and((float) $payment->amount)->toBe(236.0);
});

it('writes a kardex exit row for the POS sale (negative balance allowed)', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    $this->tenantPostJson('/api/v1/pos/checkout', basicPosCheckoutPayload($scaffold))->assertCreated();

    $exitRows = Inventory::query()
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('warehouse_id', $scaffold['warehouse']->id)
        ->where('quantity_out', '>', 0)
        ->get();

    expect($exitRows)->toHaveCount(1)
        ->and((float) $exitRows->first()->quantity_out)->toBe(2.0);
});

it('reports change when paid amount exceeds the sale total', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    $payload = basicPosCheckoutPayload($scaffold);
    $payload['payments'][0]['amount'] = 250; // overpay by 14

    $this->tenantPostJson('/api/v1/pos/checkout', $payload)
        ->assertCreated()
        ->assertJsonPath('data.change_amount', 14);
});

it('rejects a checkout when the paid amount is less than the total', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    $payload = basicPosCheckoutPayload($scaffold);
    $payload['payments'][0]['amount'] = 100; // underpay

    $this->tenantPostJson('/api/v1/pos/checkout', $payload)->assertStatus(422);
});

it('rejects a checkout against a closed session', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();
    $scaffold['session']->update(['status' => PosSession::STATUS_CLOSED, 'closed_at' => now(), 'closing_balance' => 0]);

    $this->tenantPostJson('/api/v1/pos/checkout', basicPosCheckoutPayload($scaffold))
        ->assertStatus(422);
});

it('rejects a checkout when the journal is not enabled for the POS config', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();

    // A second journal exists but is NOT attached to the POS config.
    $otherJournal = Journal::factory()->sale()->create([
        'company_id' => $scaffold['company']->id,
        'code' => 'F001',
        'sequence_id' => Sequence::factory()->create(['prefix' => 'F001'])->id,
    ]);

    $payload = basicPosCheckoutPayload($scaffold);
    $payload['journal_id'] = $otherJournal->id;

    $this->tenantPostJson('/api/v1/pos/checkout', $payload)->assertStatus(422);
});

it('rejects a checkout for a product flagged not-visible in POS', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makePosScaffold();
    $scaffold['variant']->template->update(['is_pos_visible' => false]);

    $this->tenantPostJson('/api/v1/pos/checkout', basicPosCheckoutPayload($scaffold))
        ->assertStatus(422);
});

it('requires authentication', function (): void {
    $this->tenantPostJson('/api/v1/pos/checkout', [])->assertUnauthorized();
});
