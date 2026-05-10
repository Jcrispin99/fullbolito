<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Resources\PosSessionResource;
use App\Http\Resources\SaleResource;
use App\Models\Journal;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyReward;
use App\Models\PosConfig;
use App\Models\PosSession;
use App\Models\PosSessionPayment;
use App\Models\ProductProduct;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosCheckoutService
{
    private const SALE_LOAD_RELATIONS = [
        'partner',
        'warehouse',
        'company',
        'journal',
        'user',
        'products.productProduct.template.uom',
        'products.productProduct.attributeValues',
        'products.tax',
        'products.uom',
        'originalSale.journal',
        'creditNotes.journal',
        'creditNotes.products',
        'loyaltyTransactions.program',
        'loyaltyTransactions.card',
    ];

    public function __construct(
        private readonly KardexService $kardexService,
        private readonly LoyaltyService $loyaltyService,
    ) {}

    public function checkout(array $validated, ?User $user): array
    {
        return DB::transaction(function () use ($validated, $user) {
            $session = PosSession::with(['posConfig.journals', 'posConfig.tax', 'payments.paymentMethod'])
                ->findOrFail($validated['pos_session_id']);

            if ((int) $session->pos_config_id !== (int) $validated['pos_config_id']) {
                throw ValidationException::withMessages([
                    'pos_session_id' => 'La sesión no pertenece a la caja POS indicada.',
                ]);
            }

            if (! $session->isOpen()) {
                throw ValidationException::withMessages([
                    'pos_session_id' => 'La caja seleccionada no tiene una sesión abierta.',
                ]);
            }

            $config = $session->posConfig;
            if (! $config) {
                throw ValidationException::withMessages([
                    'pos_config_id' => 'No se encontró la configuración POS asociada a la sesión.',
                ]);
            }

            $warehouseId = $this->resolveWarehouseId($validated, $config);
            $journal = $this->resolveJournal($validated['journal_id'], $config);
            $companyId = (int) $config->company_id;
            $sellerId = $user?->id;

            if (! $sellerId) {
                throw ValidationException::withMessages([
                    'seller_id' => 'No se pudo determinar el usuario que realiza el checkout.',
                ]);
            }

            $linePayloads = [];
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $salePartnerId = $validated['partner_id'] ?? $config->default_customer_id;

            foreach ($validated['items'] as $item) {
                $preparedLine = $this->prepareLineData($item);
                $product = ProductProduct::with('template')->findOrFail($preparedLine['product_product_id']);

                if (! $product->template?->is_active || ! $product->template?->is_pos_visible) {
                    throw ValidationException::withMessages([
                        'items' => "El producto {$product->display_name} no está disponible para POS.",
                    ]);
                }

                $resolvedTaxId = $preparedLine['tax_id'];
                if (! $resolvedTaxId && $config->apply_tax && $config->tax_id) {
                    $resolvedTaxId = (int) $config->tax_id;
                }

                $tax = $resolvedTaxId ? Tax::find($resolvedTaxId) : null;
                $amounts = $this->calculateLineAmounts(
                    $preparedLine['quantity'],
                    $preparedLine['price'],
                    $tax?->rate_percent ? (float) $tax->rate_percent : 0.0,
                    (bool) $config->prices_include_tax,
                );

                $linePayloads[] = [
                    'prepared' => $preparedLine,
                    'tax_id' => $resolvedTaxId,
                    'tax_rate' => $tax ? (float) $tax->rate_percent : 0.0,
                    'amounts' => $amounts,
                    'product' => $product,
                ];

                $subtotal += $amounts['subtotal'];
                $taxAmount += $amounts['tax_amount'];
            }

            $totalBeforeLoyalty = round($subtotal + $taxAmount, 2);
            [$loyaltyCard, $redeemPoints, $loyaltyDiscount] = $this->resolveLoyaltyRedemption(
                $validated,
                $salePartnerId,
                $totalBeforeLoyalty,
            );
            $total = round(max($totalBeforeLoyalty - $loyaltyDiscount, 0), 2);
            $paidAmount = round(collect($validated['payments'])->sum(fn(array $payment) => (float) $payment['amount']), 2);

            if ($paidAmount + 0.00001 < $total) {
                throw ValidationException::withMessages([
                    'payments' => 'El monto pagado es menor al total de la venta.',
                ]);
            }

            [$serie, $correlative] = $this->consumeJournalSequence($journal);

            $sale = Sale::create([
                'partner_id' => $salePartnerId,
                'warehouse_id' => $warehouseId,
                'pos_session_id' => $session->id,
                'journal_id' => $journal->id,
                'company_id' => $companyId,
                'notes' => $this->buildSaleNotes(
                    $validated['notes'] ?? null,
                    $redeemPoints,
                    $loyaltyDiscount,
                ),
                'status' => 'draft',
                'payment_status' => 'paid',
                'user_id' => $sellerId,
                'serie' => $serie,
                'correlative' => $correlative,
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxAmount, 2),
                'total' => $total,
            ]);

            foreach ($linePayloads as $line) {
                $sale->products()->create([
                    'product_product_id' => $line['prepared']['product_product_id'],
                    'quantity' => $line['prepared']['quantity'],
                    'price' => $line['amounts']['unit_price'],
                    'subtotal' => $line['amounts']['subtotal'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $line['amounts']['tax_amount'],
                    'total' => $line['amounts']['total'],
                    'uom_id' => $line['prepared']['uom_id'],
                    'quantity_uom' => $line['prepared']['quantity_uom'],
                    'price_uom' => $line['prepared']['price_uom'],
                    'uom_factor' => $line['prepared']['uom_factor'],
                    'lot_id' => $line['prepared']['lot_id'] ?? null,
                ]);
            }

            foreach ($validated['payments'] as $payment) {
                PosSessionPayment::create([
                    'pos_session_id' => $session->id,
                    'sale_id' => $sale->id,
                    'payment_method_id' => $payment['payment_method_id'],
                    'amount' => (float) $payment['amount'],
                ]);
            }

            if ($loyaltyCard && $redeemPoints > 0) {
                $this->loyaltyService->redeemPoints(
                    $loyaltyCard,
                    $redeemPoints,
                    "Canje POS - Venta {$sale->serie}-{$sale->correlative}",
                    $sale,
                );
            }

            $this->postSale($sale, $user);

            $sale->load(self::SALE_LOAD_RELATIONS);
            $session->load(['posConfig.warehouse', 'payments.paymentMethod']);

            return [
                'sale' => new SaleResource($sale),
                'session' => new PosSessionResource($session->fresh()->load(['posConfig.warehouse', 'payments.paymentMethod'])),
                'paid_amount' => $paidAmount,
                'change_amount' => round(max($paidAmount - $total, 0), 2),
                'loyalty' => [
                    'redeemed_points' => $redeemPoints,
                    'discount_amount' => $loyaltyDiscount,
                ],
            ];
        });
    }

    private function resolveLoyaltyRedemption(array $validated, ?int $partnerId, float $saleTotal): array
    {
        $redeemPoints = (int) ($validated['redeem_points'] ?? 0);

        if ($redeemPoints <= 0) {
            return [null, 0, 0.0];
        }

        if (! $partnerId) {
            throw ValidationException::withMessages([
                'partner_id' => 'Debe seleccionar un cliente para usar puntos de lealtad.',
            ]);
        }

        $cardId = $validated['loyalty_card_id'] ?? null;
        if (! $cardId) {
            throw ValidationException::withMessages([
                'loyalty_card_id' => 'Debe seleccionar una tarjeta de lealtad para canjear puntos.',
            ]);
        }

        $card = LoyaltyCard::with(['program.rewards'])
            ->where('partner_id', $partnerId)
            ->findOrFail($cardId);

        if (! $card->is_active || $card->isExpired()) {
            throw ValidationException::withMessages([
                'loyalty_card_id' => 'La tarjeta de lealtad no está disponible para canje.',
            ]);
        }

        $availablePoints = (int) floor((float) $card->points);
        if ($redeemPoints > $availablePoints) {
            throw ValidationException::withMessages([
                'redeem_points' => "Solo puede usar hasta {$availablePoints} puntos enteros.",
            ]);
        }

        $reward = $card->program?->rewards
            ->first(fn(LoyaltyReward $reward) => $reward->reward_type === 'discount'
                && $reward->discount_mode === 'per_point'
                && $reward->discount_applicability === 'order');

        if (! $reward) {
            throw ValidationException::withMessages([
                'loyalty_card_id' => 'La tarjeta no tiene una recompensa de canje POS configurada.',
            ]);
        }

        $rate = (float) $reward->discount;
        $discount = round(min($saleTotal, $redeemPoints * $rate), 2);

        // Si el canje solicitado excede el total de la venta, sólo se debitan
        // los puntos realmente aplicados (discount / rate). Defensa en profundidad
        // por si el frontend o un cliente externo envía más puntos de los necesarios.
        $effectivePoints = $rate > 0
            ? (int) floor($discount / $rate + 0.0001)
            : $redeemPoints;

        return [$card, $effectivePoints, $discount];
    }

    private function buildSaleNotes(?string $notes, int $redeemPoints, float $discountAmount): ?string
    {
        $parts = array_values(array_filter([$notes]));

        if ($redeemPoints > 0 && $discountAmount > 0) {
            $parts[] = "Canje de {$redeemPoints} puntos por S/ {$discountAmount}";
        }

        return $parts !== [] ? implode(' | ', $parts) : null;
    }

    private function resolveWarehouseId(array $validated, PosConfig $config): int
    {
        $warehouseId = $config->warehouse_id ?? $validated['warehouse_id'] ?? null;

        if (! $warehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'La caja POS no tiene un almacén asociado.',
            ]);
        }

        if (! empty($validated['warehouse_id']) && (int) $validated['warehouse_id'] !== (int) $warehouseId) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'El almacén enviado no coincide con la caja POS seleccionada.',
            ]);
        }

        return (int) $warehouseId;
    }

    private function resolveJournal(int $journalId, PosConfig $config): Journal
    {
        $journal = $config->journals->firstWhere('id', $journalId);

        if (! $journal) {
            throw ValidationException::withMessages([
                'journal_id' => 'El comprobante seleccionado no está disponible para esta caja POS.',
            ]);
        }

        return $journal;
    }

    private function consumeJournalSequence(Journal $journal): array
    {
        $serie = $journal->code ?: 'B001';
        $correlative = '0000001';

        if ($journal->sequence) {
            $serie = $journal->code;
            $correlative = str_pad(
                (string) $journal->sequence->next_number,
                $journal->sequence->sequence_size,
                '0',
                STR_PAD_LEFT,
            );

            $journal->sequence->increment('next_number', $journal->sequence->step);
        }

        return [$serie, $correlative];
    }

    private function calculateLineAmounts(
        float $quantity,
        float $submittedUnitPrice,
        float $taxRate,
        bool $pricesIncludeTax,
    ): array {
        $gross = round($quantity * $submittedUnitPrice, 2);

        if ($taxRate <= 0) {
            return [
                'unit_price' => round($submittedUnitPrice, 6),
                'subtotal' => $gross,
                'tax_amount' => 0.0,
                'total' => $gross,
            ];
        }

        $rate = $taxRate / 100;

        if ($pricesIncludeTax) {
            $unitPrice = round($submittedUnitPrice / (1 + $rate), 6);
            $subtotal = round($gross / (1 + $rate), 2);
            $taxAmount = round($gross - $subtotal, 2);

            return [
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $gross,
            ];
        }

        $taxAmount = round($gross * $rate, 2);

        return [
            'unit_price' => round($submittedUnitPrice, 6),
            'subtotal' => $gross,
            'tax_amount' => $taxAmount,
            'total' => round($gross + $taxAmount, 2),
        ];
    }

    private function postSale(Sale $sale, ?User $user): void
    {
        if ($sale->status !== 'draft') {
            throw ValidationException::withMessages([
                'sale' => 'Solo se pueden publicar ventas en borrador.',
            ]);
        }

        $sale->update(['status' => 'posted']);

        activity()
            ->performedOn($sale)
            ->causedBy($user)
            ->log('Venta POS publicada');

        $sale->load('products.productProduct.template');

        foreach ($sale->products as $productable) {
            $tracksInventory = $productable->productProduct?->template?->tracks_inventory ?? true;
            if (! $tracksInventory) {
                continue;
            }

            // Selección manual del cajero → allocation explícita, si no FEFO automático.
            $allocations = $productable->lot_id !== null
                ? [['lot_id' => (int) $productable->lot_id, 'quantity' => (float) $productable->quantity]]
                : null;

            if ($allocations !== null) {
                // En POS la venta de vencidos puede estar permitida si la config lo habilita.
                $allowExpired = (bool) ($sale->posSession?->posConfig?->allow_expired_sale_with_override ?? false);
                $this->kardexService->assertLotSellable((int) $productable->lot_id, $allowExpired);
            }

            $used = $this->kardexService->registerExit(
                $sale,
                [
                    'id' => $productable->product_product_id,
                    'quantity' => $productable->quantity,
                ],
                (int) $sale->warehouse_id,
                "Venta {$sale->serie}-{$sale->correlative}",
                $allocations,
                allowNegativeStock: true,
            );

            // Persistir lot_id en línea cuando FEFO consumió un único lote (trazabilidad UI).
            if ($productable->lot_id === null && count($used) === 1) {
                $productable->lot_id = $used[0]['lot_id'];
                $productable->save();
            }
        }

        if ($sale->partner_id) {
            $this->loyaltyService->processSale($sale, $sale->partner, 'pos');
        }
    }

    private function prepareLineData(array $item): array
    {
        $uomId = isset($item['uom_id']) ? (int) $item['uom_id'] : null;

        if ($uomId) {
            $uom = UnitOfMeasure::findOrFail($uomId);
            $factor = $uom->factorToBase();

            $quantityUom = (float) $item['quantity_uom'];
            $priceUom = (float) $item['price_uom'];

            return [
                'product_product_id' => (int) $item['product_product_id'],
                'quantity' => $quantityUom * $factor,
                'price' => $priceUom / $factor,
                'tax_id' => $item['tax_id'] ?? null,
                'lot_id' => isset($item['lot_id']) ? (int) $item['lot_id'] : null,
                'uom_id' => $uomId,
                'quantity_uom' => $quantityUom,
                'price_uom' => $priceUom,
                'uom_factor' => $factor,
            ];
        }

        return [
            'product_product_id' => (int) $item['product_product_id'],
            'quantity' => (float) $item['quantity'],
            'price' => (float) $item['price'],
            'tax_id' => $item['tax_id'] ?? null,
            'lot_id' => isset($item['lot_id']) ? (int) $item['lot_id'] : null,
            'uom_id' => null,
            'quantity_uom' => null,
            'price_uom' => null,
            'uom_factor' => 1,
        ];
    }
}
