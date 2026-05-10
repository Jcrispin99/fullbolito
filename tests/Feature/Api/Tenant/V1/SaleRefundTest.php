<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Company;
use App\Models\Inventory;
use App\Models\Journal;
use App\Models\Lot;
use App\Models\PaymentMethod;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Sale;
use App\Models\Sequence;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\KardexService;
use App\Services\SaleRefundService;
use Illuminate\Validation\ValidationException;

/**
 * Build a POS scaffold + a credit-note journal attached to the same company.
 * Returns the scaffold plus the credit_note journal so tests can assert
 * sequence consumption on it.
 */
function makeRefundScaffold(): array
{
    $company = Company::factory()->create();
    $warehouse = Warehouse::factory()->for($company)->create();

    $saleSequence = Sequence::factory()->create(['prefix' => 'B001']);
    $saleJournal = Journal::factory()->sale()->create([
        'company_id' => $company->id,
        'code' => 'B001',
        'sequence_id' => $saleSequence->id,
    ]);

    $creditSequence = Sequence::factory()->create(['prefix' => 'FC01']);
    $creditJournal = Journal::factory()->creditNote()->create([
        'company_id' => $company->id,
        'code' => 'FC01',
        'sequence_id' => $creditSequence->id,
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
    $posConfig->journals()->attach($saleJournal->id, ['document_type' => 'receipt', 'is_default' => true]);
    $posConfig->journals()->attach($creditJournal->id, ['document_type' => 'credit_note', 'is_default' => false]);

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

    return compact(
        'company',
        'warehouse',
        'saleJournal',
        'creditJournal',
        'tax',
        'posConfig',
        'session',
        'variant',
        'cash',
    );
}

/**
 * Posts a sale through the real POS checkout endpoint to get a realistic
 * Sale + kardex exit + payment row. Returns the Sale model.
 */
function postOriginalSaleViaPos(array $scaffold, float $quantity = 2): Sale
{
    test()->actingAsTenantUser();

    $unitPrice = 100;
    $subtotal = $quantity * $unitPrice;
    $tax = $subtotal * 0.18;
    $total = $subtotal + $tax;

    test()->tenantPostJson('/api/v1/pos/checkout', [
        'pos_config_id' => $scaffold['posConfig']->id,
        'pos_session_id' => $scaffold['session']->id,
        'journal_id' => $scaffold['saleJournal']->id,
        'items' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => $quantity,
                'price' => $unitPrice,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $scaffold['cash']->id,
                'amount' => $total,
            ],
        ],
    ])->assertCreated();

    return Sale::query()->whereNull('original_sale_id')->latest('id')->first();
}

it('creates a posted credit note linked to the original via original_sale_id', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 2);

    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 2],
        ],
        'pos_session_id' => $scaffold['session']->id,
    ], $original->user);

    expect($note->original_sale_id)->toBe($original->id)
        ->and($note->status)->toBe('posted')
        ->and($note->payment_status)->toBe('paid')
        ->and($note->serie)->toBe('FC01')
        ->and((float) $note->total)->toBe((float) $original->total)
        ->and($note->journal_id)->toBe($scaffold['creditJournal']->id);
});

it('writes a kardex entry returning inventory to the original lot', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 2);

    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 2],
        ],
    ], $original->user);

    $entry = Inventory::query()
        ->where('inventoryable_type', (new Sale)->getMorphClass())
        ->where('inventoryable_id', $note->id)
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('quantity_in', '>', 0)
        ->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->quantity_in)->toBe(2.0);
});

it('rejects a refund when the sale is not posted', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold);
    $original->update(['status' => 'draft']);

    $service = app(SaleRefundService::class);

    expect(fn () => $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 1]],
    ], $original->user))->toThrow(ValidationException::class);
});

it('rejects a refund on top of an existing credit note', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold);

    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 1]],
    ], $original->user);

    expect(fn () => $service->createFromOriginal($note, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 1]],
    ], $original->user))->toThrow(ValidationException::class);
});

it('rejects a refund when quantity exceeds original line quantity', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 2);

    $service = app(SaleRefundService::class);

    expect(fn () => $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 3]],
    ], $original->user))->toThrow(ValidationException::class);
});

it('rejects a refund when the product is not in the original sale', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold);

    $otherTemplate = ProductTemplate::factory()->create([
        'category_id' => Category::factory()->create()->id,
        'uom_id' => $scaffold['variant']->template->uom_id,
    ]);
    $otherVariant = ProductProduct::factory()->principal()->create([
        'product_template_id' => $otherTemplate->id,
    ]);

    $service = app(SaleRefundService::class);

    expect(fn () => $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $otherVariant->id, 'quantity' => 1]],
    ], $original->user))->toThrow(ValidationException::class);
});

it('allows multiple partial refunds adding up to the original quantity', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 5);

    $service = app(SaleRefundService::class);

    $note1 = $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 2]],
    ], $original->user);

    $note2 = $service->createFromOriginal($original->fresh(), [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 3]],
    ], $original->user);

    expect((float) $note1->total + (float) $note2->total)->toEqualWithDelta((float) $original->total, 0.02);
});

it('rejects a refund when accumulated quantity would exceed original', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 5);

    $service = app(SaleRefundService::class);
    $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 4]],
    ], $original->user);

    expect(fn () => $service->createFromOriginal($original->fresh(), [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 2]],
    ], $original->user))->toThrow(ValidationException::class);
});

it('consumes the credit note journal sequence', function (): void {
    $scaffold = makeRefundScaffold();
    $initial = (int) $scaffold['creditJournal']->sequence->next_number;

    $original = postOriginalSaleViaPos($scaffold);

    $service = app(SaleRefundService::class);
    $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 1]],
    ], $original->user);

    expect((int) $scaffold['creditJournal']->sequence->fresh()->next_number)
        ->toBe($initial + (int) $scaffold['creditJournal']->sequence->step);
});

it('computes the proportional total for a partial refund', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 4);

    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 1]],
    ], $original->user);

    $expectedTotal = round((float) $original->total / 4, 2);

    expect((float) $note->total)->toEqualWithDelta($expectedTotal, 0.02);
});

it('exposes computeAlreadyRefunded reflecting posted credit notes', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 5);

    $service = app(SaleRefundService::class);
    $service->createFromOriginal($original, [
        'lines' => [['product_product_id' => $scaffold['variant']->id, 'quantity' => 2]],
    ], $original->user);

    $summary = $service->computeAlreadyRefunded($original->fresh());

    expect($summary['by_line'][$scaffold['variant']->id]['quantity'])->toBe(2.0);
});

// ─── POST /api/v1/pos/refunds endpoint tests ─────────────────────────────────

it('creates a credit note via the POS refunds endpoint', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold, quantity: 2);

    $response = $this->tenantPostJson('/api/v1/pos/refunds', [
        'original_sale_id' => $original->id,
        'pos_session_id' => $scaffold['session']->id,
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 1],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'posted')
        ->assertJsonPath('data.original_sale.id', $original->id);
});

it('rejects refund endpoint when session is closed', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold);
    $scaffold['session']->update([
        'status' => 'closed',
        'closed_at' => now(),
        'closing_balance' => 0,
    ]);

    $this->tenantPostJson('/api/v1/pos/refunds', [
        'original_sale_id' => $original->id,
        'pos_session_id' => $scaffold['session']->id,
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 1],
        ],
    ])->assertStatus(422);
});

it('rejects refund endpoint when validation fails (missing lines)', function (): void {
    $scaffold = makeRefundScaffold();
    $original = postOriginalSaleViaPos($scaffold);

    $this->tenantPostJson('/api/v1/pos/refunds', [
        'original_sale_id' => $original->id,
        'pos_session_id' => $scaffold['session']->id,
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lines']);
});

it('returns inventory to the correct lot when the original sale used a lot-tracked product', function (): void {
    $scaffold = makeRefundScaffold();

    // Make the existing variant lot-tracked.
    $scaffold['variant']->template->update(['tracked_by_lot' => true]);

    // Create a lot with stock for this product in the warehouse.
    $lot = Lot::factory()->active()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'expires_at' => now()->addMonths(6)->toDateString(),
    ]);

    $movement = \App\Models\Movement::factory()->entry()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
    ]);
    $kardex = app(KardexService::class);
    $kardex->registerEntry(
        $movement,
        [
            'id' => $scaffold['variant']->id,
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
        ],
        $scaffold['warehouse']->id,
        'Stock inicial para test',
        $lot->id,
    );

    // POS checkout: sells 4 units (FEFO will pick this lot).
    test()->actingAsTenantUser();
    test()->tenantPostJson('/api/v1/pos/checkout', [
        'pos_config_id' => $scaffold['posConfig']->id,
        'pos_session_id' => $scaffold['session']->id,
        'journal_id' => $scaffold['saleJournal']->id,
        'items' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 4,
                'price' => 100,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
        'payments' => [
            ['payment_method_id' => $scaffold['cash']->id, 'amount' => 472],
        ],
    ])->assertCreated();

    $original = Sale::query()->whereNull('original_sale_id')->latest('id')->first();

    // Refund 2 units. They must come back to the same lot in the kardex.
    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 2],
        ],
    ], $original->user);

    $entry = Inventory::query()
        ->where('inventoryable_type', (new Sale)->getMorphClass())
        ->where('inventoryable_id', $note->id)
        ->where('product_product_id', $scaffold['variant']->id)
        ->where('quantity_in', '>', 0)
        ->first();

    expect($entry)->not->toBeNull()
        ->and((float) $entry->quantity_in)->toBe(2.0)
        ->and((int) $entry->lot_id)->toBe($lot->id);
});

it('exposes lot breakdown in refunds_summary when the original used a lot-tracked product', function (): void {
    $scaffold = makeRefundScaffold();
    $scaffold['variant']->template->update(['tracked_by_lot' => true]);

    $lot = Lot::factory()->active()->create([
        'product_product_id' => $scaffold['variant']->id,
        'company_id' => $scaffold['company']->id,
        'lot_number' => 'LOT-TEST-001',
        'expires_at' => '2027-01-15',
    ]);

    $movement = \App\Models\Movement::factory()->entry()->posted()->create([
        'warehouse_id' => $scaffold['warehouse']->id,
        'company_id' => $scaffold['company']->id,
    ]);
    $kardex = app(KardexService::class);
    $kardex->registerEntry(
        $movement,
        [
            'id' => $scaffold['variant']->id,
            'quantity' => 10,
            'price' => 50,
            'subtotal' => 500,
        ],
        $scaffold['warehouse']->id,
        'Stock inicial',
        $lot->id,
    );

    test()->actingAsTenantUser();
    test()->tenantPostJson('/api/v1/pos/checkout', [
        'pos_config_id' => $scaffold['posConfig']->id,
        'pos_session_id' => $scaffold['session']->id,
        'journal_id' => $scaffold['saleJournal']->id,
        'items' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 3,
                'price' => 100,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
        'payments' => [
            ['payment_method_id' => $scaffold['cash']->id, 'amount' => 354],
        ],
    ])->assertCreated();

    $original = Sale::query()->whereNull('original_sale_id')->latest('id')->first();

    // Without any refund yet, the resource should expose the lot breakdown.
    $response = test()->tenantGetJson("/api/v1/sales/{$original->id}");
    $response->assertOk();

    $lots = $response->json('data.refunds_summary.lines.0.lots');
    expect($lots)->toBeArray()->toHaveCount(1)
        ->and($lots[0]['lot_id'])->toBe($lot->id)
        ->and($lots[0]['lot_number'])->toBe('LOT-TEST-001')
        ->and($lots[0]['expires_at'])->toBe('2027-01-15')
        ->and((float) $lots[0]['sold_quantity'])->toBe(3.0)
        ->and((float) $lots[0]['refunded_quantity'])->toBe(0.0)
        ->and((float) $lots[0]['available_quantity'])->toBe(3.0);
});

it('picks the credit-note journal whose affects_document_type_code matches the original', function (): void {
    $scaffold = makeRefundScaffold();

    // Add an invoice sale journal (doc 01) and an FC credit note for factura.
    $invoiceSeq = Sequence::factory()->create(['prefix' => 'F001']);
    $invoiceJournal = Journal::factory()->invoice()->create([
        'company_id' => $scaffold['company']->id,
        'code' => 'F001',
        'sequence_id' => $invoiceSeq->id,
    ]);

    $fcSeq = Sequence::factory()->create(['prefix' => 'FC02']);
    $fcJournal = Journal::factory()->creditNote()->affectsInvoice()->create([
        'company_id' => $scaffold['company']->id,
        'code' => 'FC02',
        'sequence_id' => $fcSeq->id,
    ]);

    // Attach both NC journals to the same PosConfig.
    $scaffold['posConfig']->journals()->attach($invoiceJournal->id, [
        'document_type' => 'invoice',
        'is_default' => false,
    ]);
    $scaffold['posConfig']->journals()->attach($fcJournal->id, [
        'document_type' => 'credit_note',
        'is_default' => false,
    ]);

    test()->actingAsTenantUser();
    test()->tenantPostJson('/api/v1/pos/checkout', [
        'pos_config_id' => $scaffold['posConfig']->id,
        'pos_session_id' => $scaffold['session']->id,
        'journal_id' => $invoiceJournal->id,
        'items' => [
            [
                'product_product_id' => $scaffold['variant']->id,
                'quantity' => 1,
                'price' => 100,
                'tax_id' => $scaffold['tax']->id,
            ],
        ],
        'payments' => [
            ['payment_method_id' => $scaffold['cash']->id, 'amount' => 118],
        ],
    ])->assertCreated();

    $original = Sale::query()
        ->where('journal_id', $invoiceJournal->id)
        ->latest('id')
        ->first();

    $service = app(SaleRefundService::class);
    $note = $service->createFromOriginal($original, [
        'lines' => [
            ['product_product_id' => $scaffold['variant']->id, 'quantity' => 1],
        ],
        'pos_session_id' => $scaffold['session']->id,
    ], $original->user);

    expect($note->journal_id)->toBe($fcJournal->id)
        ->and($note->serie)->toBe('FC02');
});
