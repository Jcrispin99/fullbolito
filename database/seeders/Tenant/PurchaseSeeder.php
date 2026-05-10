<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Journal;
use App\Models\Partner;
use App\Models\ProductProduct;
use App\Models\Purchase;
use App\Models\Tax;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Purchase::query()->exists()) {
            return;
        }

        $journal = Journal::where('type', 'purchase')->first();
        $supplier = Partner::suppliers()->first();
        $warehouse = Warehouse::first();
        $company = Company::first();
        $buyer = User::first();
        $tax = Tax::where('rate_percent', 18)->where('is_active', true)->first();
        $products = ProductProduct::with('productTemplate')->take(10)->get();

        if (! $journal || ! $supplier || ! $warehouse || ! $company || $products->isEmpty()) {
            $this->command->error('Faltan datos base para crear compras. Verifica journals, partners, warehouses y productos.');
            return;
        }

        $sequence = $journal->sequence;

        for ($i = 0; $i < 5; $i++) {
            $correlativeNumber = $sequence->next_number;
            $sequence->increment('next_number', $sequence->step);

            $purchase = Purchase::create([
                'serie' => $journal->code,
                'correlative' => str_pad((string) $correlativeNumber, $sequence->sequence_size, '0', STR_PAD_LEFT),
                'journal_id' => $journal->id,
                'date' => now()->subDays(rand(1, 30)),
                'partner_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'company_id' => $company->id,
                'buyer_id' => $buyer->id,
                'total' => 0,
                'status' => 'posted',
                'payment_status' => 'paid',
                'vendor_bill_number' => 'F001-' . str_pad((string) ($i + 1), 8, '0', STR_PAD_LEFT),
                'vendor_bill_date' => now()->subDays(rand(1, 30)),
            ]);

            $lineCount = rand(2, 4);
            $purchaseTotal = 0;
            $selectedProducts = $products->random(min($lineCount, $products->count()));

            foreach ($selectedProducts as $product) {
                $quantity = rand(5, 50);
                $price = (float) ($product->price ?? $product->productTemplate->price ?? rand(10, 100));
                $costPrice = round($price * 0.7, 2); // costo = 70% del precio venta
                $subtotal = round($quantity * $costPrice, 2);
                $taxRate = $tax ? (float) $tax->rate_percent : 0;
                $taxAmount = round($subtotal * $taxRate / 100, 2);
                $total = $subtotal + $taxAmount;

                $purchase->productables()->create([
                    'product_product_id' => $product->id,
                    'quantity' => $quantity,
                    'quantity_uom' => $quantity,
                    'price' => $costPrice,
                    'price_uom' => $costPrice,
                    'subtotal' => $subtotal,
                    'tax_id' => $tax?->id,
                    'tax_rate' => $taxRate,
                    'tax_amount' => $taxAmount,
                    'total' => $total,
                ]);

                // Registrar movimiento de inventario (entrada)
                $lastInventory = $product->inventories()
                    ->where('warehouse_id', $warehouse->id)
                    ->latest()
                    ->first();

                $prevBalance = $lastInventory ? (float) $lastInventory->quantity_balance : 0;
                $prevCostBalance = $lastInventory ? (float) $lastInventory->total_balance : 0;
                $newBalance = $prevBalance + $quantity;
                $newTotalBalance = $prevCostBalance + $subtotal;
                $newCostBalance = $newBalance > 0 ? round($newTotalBalance / $newBalance, 2) : 0;

                $purchase->inventories()->create([
                    'detail' => "Compra {$purchase->serie}-{$purchase->correlative}",
                    'quantity_in' => $quantity,
                    'cost_in' => $costPrice,
                    'total_in' => $subtotal,
                    'quantity_out' => 0,
                    'cost_out' => 0,
                    'total_out' => 0,
                    'quantity_balance' => $newBalance,
                    'cost_balance' => $newCostBalance,
                    'total_balance' => $newTotalBalance,
                    'product_product_id' => $product->id,
                    'warehouse_id' => $warehouse->id,
                ]);

                $purchaseTotal += $total;
            }

            $purchase->update(['total' => round($purchaseTotal, 2)]);
        }

        $this->command->info('Se crearon 5 compras con líneas e inventario.');

        $this->resyncSequences();
    }

    /**
     * Alinea sequences.next_number con MAX(correlative)+step por journal de compras,
     * para evitar 'Duplicate entry' si se mezclan seeders y creación runtime.
     */
    private function resyncSequences(): void
    {
        $journalIds = Purchase::query()->distinct()->pluck('journal_id')->filter()->all();

        foreach ($journalIds as $journalId) {
            $journal = Journal::with('sequence')->find($journalId);
            if (! $journal || ! $journal->sequence) {
                continue;
            }

            $maxCorrelative = (int) Purchase::query()
                ->where('journal_id', $journalId)
                ->max(DB::raw('CAST(correlative AS UNSIGNED)'));

            $next = $maxCorrelative + (int) $journal->sequence->step;

            if ($next > (int) $journal->sequence->next_number) {
                $journal->sequence->update(['next_number' => $next]);
            }
        }
    }
}
