<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PaymentMethod;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sale;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\GreenterInvoiceService;
use Tests\Doubles\FakeGreenterInvoiceService;

/**
 * Build the minimum scaffold needed to create/post sales:
 *  - company + warehouse
 *  - sale journal (doc 03 — boleta) with its sequence
 *  - tax IGV 18%
 *  - one product (service so kardex is skipped on post)
 *  - a customer partner
 */
function makeSaleScaffold(array $overrides = []): array
{
    $company = $overrides['company'] ?? Company::factory()->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    $sequence = Sequence::factory()->create(['prefix' => 'B001']);
    $journal = Journal::factory()->sale()->create([
        'company_id' => $company->id,
        'code' => 'B001',
        'sequence_id' => $sequence->id,
    ]);

    $tax = Tax::factory()->igv()->default()->create();

    $category = Category::factory()->create();
    $uom = UnitOfMeasure::factory()->unit()->create();
    $template = ProductTemplate::factory()->service()->create([
        'category_id' => $category->id,
        'uom_id' => $uom->id,
    ]);
    $variant = ProductProduct::factory()->principal()->create([
        'product_template_id' => $template->id,
        'price' => 100,
        'cost_price' => 50,
    ]);

    $customer = Partner::factory()->customer()->create();

    return compact('company', 'warehouse', 'journal', 'sequence', 'tax', 'variant', 'customer');
}

function basicSalePayload(array $scaffold): array
{
    return [
        'partner_id' => $scaffold['customer']->id,
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'products' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 2,
                'price' => 100,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
    ];
}

it('requires authentication to list sales', function (): void {
    $response = $this->tenantGetJson('/api/v1/sales');
    $response->assertUnauthorized();
});

it('lists sales for the authenticated user', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    Sale::factory()
        ->count(3)
        ->for($scaffold['warehouse'])
        ->for($scaffold['company'])
        ->for($scaffold['journal'])
        ->for($this->tenantUser)
        ->create();

    $response = $this->tenantGetJson('/api/v1/sales');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(3, 'data');
});

it('creates a sale as draft with computed totals', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $response = $this->tenantPostJson('/api/v1/sales', basicSalePayload($scaffold));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payment_status', 'unpaid');

    $sale = Sale::query()->latest('id')->first();
    expect((float) $sale->subtotal)->toBe(200.0)
        ->and((float) $sale->tax_amount)->toBe(36.0)
        ->and((float) $sale->total)->toBe(236.0)
        ->and($sale->serie)->toBe('B001');
});

it('does not allow deleting a posted sale', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();
    $sale = Sale::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $response = $this->tenantDeleteJson("/api/v1/sales/{$sale->id}");

    $response->assertStatus(422);
    expect(Sale::find($sale->id))->not->toBeNull();
});

it('deletes a draft sale', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();
    $sale = Sale::factory()->draft()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $response = $this->tenantDeleteJson("/api/v1/sales/{$sale->id}");

    $response->assertNoContent();
    expect(Sale::find($sale->id))->toBeNull();
});

it('posts a draft sale and dispatches sunat job (mocked)', function (): void {
    $fake = $this->mockGreenterInvoice();

    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    // Create draft via API first (to get all relations + lines)
    $createRes = $this->tenantPostJson('/api/v1/sales', basicSalePayload($scaffold));
    $createRes->assertCreated();
    $sale = Sale::query()->latest('id')->first();
    expect($sale->status)->toBe('draft');

    $response = $this->tenantPatchJson("/api/v1/sales/{$sale->id}/post");

    $response->assertOk()->assertJsonPath('data.status', 'posted');
    expect($sale->fresh()->sunat_status)->toBe('accepted')
        ->and($fake->sentSaleIds)->toContain($sale->id);
});

it('cancels a posted sale (service product — no kardex impact)', function (): void {
    $this->mockGreenterInvoice();
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    // Create + post via API to build full state.
    $this->tenantPostJson('/api/v1/sales', basicSalePayload($scaffold))->assertCreated();
    $sale = Sale::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/sales/{$sale->id}/post")->assertOk();

    $response = $this->tenantPatchJson("/api/v1/sales/{$sale->id}/cancel");

    $response->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($sale->fresh()->status)->toBe('cancelled');
});

it('marks a posted unpaid sale as paid', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->posted()->unpaid()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $response = $this->tenantPatchJson("/api/v1/sales/{$sale->id}/pay");

    $response->assertOk()->assertJsonPath('data.payment_status', 'paid');
    expect($sale->fresh()->payment_status)->toBe('paid');
});

it('rejects pay request on a draft sale', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->draft()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $this->tenantPatchJson("/api/v1/sales/{$sale->id}/pay")->assertStatus(422);
});

it('manually resends a posted sale to SUNAT and persists accepted status', function (): void {
    $fake = $this->mockGreenterInvoice();
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
        'sunat_status' => 'error',
    ]);

    $response = $this->tenantPostJson("/api/v1/sales/{$sale->id}/sunat/send");

    $response->assertOk();
    expect($sale->fresh()->sunat_status)->toBe('accepted')
        ->and($fake->sentSaleIds)->toContain($sale->id);
});

it('persists error status when SUNAT call fails (mocked)', function (): void {
    $this->mockGreenterInvoice(shouldSucceed: false);
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $this->tenantPostJson("/api/v1/sales/{$sale->id}/sunat/send")->assertOk();

    expect($sale->fresh()->sunat_status)->toBe('error');
});

it('emits a posted credit note refund from a posted sale', function (): void {
    $this->mockGreenterInvoice();
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    // Provision credit note journal (doc 07).
    $creditSequence = Sequence::factory()->create(['prefix' => 'FC01']);
    Journal::factory()->creditNote()->create([
        'company_id' => $scaffold['company']->id,
        'code' => 'FC01',
        'sequence_id' => $creditSequence->id,
    ]);

    $this->tenantPostJson('/api/v1/sales', basicSalePayload($scaffold))->assertCreated();
    $sale = Sale::query()->latest('id')->first();
    $this->tenantPatchJson("/api/v1/sales/{$sale->id}/post")->assertOk();

    $saleLine = $sale->products()->first();

    $response = $this->tenantPostJson("/api/v1/sales/{$sale->id}/refunds", [
        'lines' => [
            ['product_product_id' => $saleLine->product_product_id, 'quantity' => $saleLine->quantity],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.original_sale.id', $sale->id);

    $credit = Sale::query()->where('original_sale_id', $sale->id)->first();
    expect($credit)->not->toBeNull()
        ->and($credit->serie)->toBe('FC01')
        ->and((float) $credit->total)->toBe((float) $sale->total);
});

it('does not allow creating a refund from a draft sale', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->draft()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
    ]);

    $this->tenantPostJson("/api/v1/sales/{$sale->id}/refunds", [
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 1],
        ],
    ])->assertStatus(422);
});

it('returns 404 sunat XML download when sale has none', function (): void {
    $this->actingAsTenantUser();
    $scaffold = makeSaleScaffold();

    $sale = Sale::factory()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
        'journal_id' => $scaffold['journal']->id,
        'user_id' => $this->tenantUser->id,
        'signed_xml_path' => null,
    ]);

    $this->tenantGetJson("/api/v1/sales/{$sale->id}/sunat/xml")->assertStatus(404);
});
