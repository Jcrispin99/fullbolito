<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\PaymentMethod;
use App\Models\PosSession;
use App\Models\PosSessionPayment;
use App\Models\ProductProduct;
use App\Models\Sale;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class SaleSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::query()->exists()) {
            return;
        }

        $boletaJournal = Journal::where('code', 'B004')->first();
        $facturaJournal = Journal::where('code', 'F004')->first();
        $nvJournal = Journal::where('code', 'NV')->first();
        $customer = Partner::customers()->first();
        $warehouse = Warehouse::first();
        $company = Company::first();
        $user = User::first();
        $tax = Tax::where('rate_percent', 18)->where('is_active', true)->first();
        $posSession = PosSession::where('status', PosSession::STATUS_OPENED)->first();
        $cashMethod = PaymentMethod::where('name', 'Efectivo')->first();
        $products = ProductProduct::with('productTemplate')->take(10)->get();

        $saleJournal = $boletaJournal ?? $facturaJournal ?? $nvJournal;

        if (! $saleJournal || ! $warehouse || ! $company || ! $user || $products->isEmpty()) {
            $this->command->error('Faltan datos base para crear ventas. Verifica journals, warehouses, users y productos.');
            return;
        }

        $sequence = $saleJournal->sequence;

        for ($i = 0; $i < 8; $i++) {
            $correlativeNumber = $sequence->next_number;
            $sequence->increment('next_number', $sequence->step);

            $isPosSale = $posSession && $i < 5; // 5 ventas POS, 3 directas

            $sale = Sale::create([
                'serie' => $saleJournal->code,
                'correlative' => str_pad((string) $correlativeNumber, $sequence->sequence_size, '0', STR_PAD_LEFT),
                'journal_id' => $saleJournal->id,
                'date' => now()->subHours(rand(1, 72)),
                'partner_id' => $customer?->id,
                'warehouse_id' => $warehouse->id,
                'company_id' => $company->id,
                'pos_session_id' => $isPosSale ? $posSession->id : null,
                'user_id' => $user->id,
                'subtotal' => 0,
                'tax_amount' => 0,
                'total' => 0,
                'status' => 'posted',
                'payment_status' => 'paid',
            ]);

            $lineCount = rand(1, 3);
            $saleSubtotal = 0;
            $saleTaxAmount = 0;
            $selectedProducts = $products->random(min($lineCount, $products->count()));

            foreach ($selectedProducts as $product) {
                $quantity = rand(1, 5);
                $price = (float) ($product->price ?? $product->productTemplate->price ?? rand(20, 200));
                $subtotal = round($quantity * $price, 2);
                $taxRate = $tax ? (float) $tax->rate_percent : 0;
                $taxAmount = round($subtotal * $taxRate / 100, 2);
                $total = $subtotal + $taxAmount;

                $sale->products()->create([
                    'product_product_id' => $product->id,
                    'quantity' => $quantity,
                    'quantity_uom' => $quantity,
                    'price' => $price,
                    'price_uom' => $price,
                    'subtotal' => $subtotal,
                    'tax_id' => $tax?->id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                // Registrar movimiento de inventario (salida)
                $lastInventory = $product->inventories()
                    ->where('warehouse_id', $warehouse->id)
                    ->latest()
                    ->first();

                $prevBalance = $lastInventory ? (float) $lastInventory->quantity_balance : 0;
                $prevCostBalance = $lastInventory ? (float) $lastInventory->total_balance : 0;
                $prevCost = $lastInventory ? (float) $lastInventory->cost_balance : $price;
                $costOut = $prevCost;
                $totalOut = round($quantity * $costOut, 2);
                $newBalance = $prevBalance - $quantity;
                $newTotalBalance = $prevCostBalance - $totalOut;
                $newCostBalance = $newBalance > 0 ? round($newTotalBalance / $newBalance, 2) : 0;

                $sale->inventories()->create([
                    'detail' => "Venta {$sale->serie}-{$sale->correlative}",
                    'quantity_in' => 0,
                    'cost_in' => 0,
                    'total_in' => 0,
                    'quantity_out' => $quantity,
                    'cost_out' => $costOut,
                    'total_out' => $totalOut,
                    'quantity_balance' => max($newBalance, 0),
                    'cost_balance' => max($newCostBalance, 0),
                    'total_balance' => max($newTotalBalance, 0),
                    'product_product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $saleSubtotal += $subtotal;
                $saleTaxAmount += $taxAmount;
            }

            $saleTotal = round($saleSubtotal + $saleTaxAmount, 2);
            $sale->update([
                'subtotal' => round($saleSubtotal, 2),
                'tax_amount' => round($saleTaxAmount, 2),
                'total' => $saleTotal,
            ]);

            // Registrar pago POS
            if ($isPosSale && $cashMethod) {
                PosSessionPayment::create([
                    'pos_session_id' => $posSession->id,
                    'sale_id' => $sale->id,
                    'payment_method_id' => $cashMethod->id,
                    'amount' => $saleTotal,
                ]);
            }
        }

        $this->command->info('Se crearon 8 ventas (5 POS + 3 directas) con líneas, inventario y pagos.');

        $this->resyncSequences();
    }

    /**
     * Alinea sequences.next_number con MAX(correlative)+step por journal de ventas,
     * para evitar 'Duplicate entry' si se mezclan seeders y creación runtime.
     */
    private function resyncSequences(): void
    {
        $journalIds = Sale::query()->distinct()->pluck('journal_id')->filter()->all();

        foreach ($journalIds as $journalId) {
            $journal = Journal::with('sequence')->find($journalId);
            if (! $journal || ! $journal->sequence) {
                continue;
            }

            $maxCorrelative = (int) Sale::query()
                ->where('journal_id', $journalId)
                ->max(DB::raw('CAST(correlative AS UNSIGNED)'));

            $next = $maxCorrelative + (int) $journal->sequence->step;

            if ($next > (int) $journal->sequence->next_number) {
                $journal->sequence->update(['next_number' => $next]);
            }
        }
    }
}
