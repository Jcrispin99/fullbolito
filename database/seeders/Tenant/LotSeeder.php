<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Models\Company;
use App\Models\Inventory;
use App\Models\Lot;
use App\Models\LotInventory;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LotSeeder extends Seeder
{
    /**
     * Crea productos con trazabilidad por lote, lotes con distintas fechas
     * de vencimiento, y distribuye stock entre los almacenes existentes —
     * de modo que las pantallas de Lotes y Transferencias tengan datos reales.
     */
    public function run(): void
    {
        if (Lot::query()->exists()) {
            return;
        }

        $company = Company::whereNull('parent_id')->first() ?? Company::first();
        /** @var Collection<int, Warehouse> $warehouses */
        $warehouses = Warehouse::orderBy('id')->get();

        if (! $company || $warehouses->isEmpty()) {
            $this->command->error('Se requiere al menos 1 warehouse y 1 company. Ejecuta CompanySeeder y WarehouseSeeder primero.');

            return;
        }

        // Tomar 5 product templates para marcarlos como trazados por lote
        $templates = ProductTemplate::query()
            ->whereHas('productProducts')
            ->orderBy('id')
            ->take(5)
            ->get();

        if ($templates->isEmpty()) {
            $this->command->error('No hay product templates. Ejecuta ProductSeeder primero.');

            return;
        }

        DB::transaction(function () use ($templates, $warehouses, $company) {
            $totalLots = 0;
            $totalMovements = 0;

            foreach ($templates as $template) {
                // Marcar como trazado por lote
                $template->update(['tracked_by_lot' => true]);

                // Elegir la variante principal (o la primera)
                /** @var ProductProduct|null $variant */
                $variant = $template->productProducts()
                    ->where('is_principal', true)
                    ->first()
                    ?? $template->productProducts()->first();

                if (! $variant instanceof ProductProduct) {
                    continue;
                }

                // Crear 3 lotes con fechas de fabricación/vencimiento escalonadas
                $lotCount = 3;
                for ($i = 0; $i < $lotCount; $i++) {
                    $lotNumber = sprintf('LOT-%03d-%03d', $template->id, $i + 1);
                    $manufacturedAt = now()->subMonths($i + 1);
                    // Vencimientos: primer lote vence primero (6 meses), luego 12m, luego 18m
                    $expiresAt = now()->addMonths(($lotCount - $i) * 6);

                    $initialQty = (float) random_int(30, 80);
                    $variantPrice = (float) ($variant->price ?? $template->price ?? 50);
                    $initialCost = round($variantPrice * 0.7, 4);

                    $lot = Lot::create([
                        'product_product_id' => $variant->id,
                        'company_id' => $company->id,
                        'lot_number' => $lotNumber,
                        'manufactured_at' => $manufacturedAt,
                        'expires_at' => $expiresAt,
                        'supplier_id' => null,
                        'purchase_id' => null,
                        'initial_quantity' => $initialQty,
                        'initial_cost' => $initialCost,
                        'status' => 'active',
                        'notes' => 'Seed lot',
                    ]);

                    // Repartir stock entre almacenes: ~70% primer almacén, resto al segundo
                    // Si solo hay 1 almacén, todo va ahí.
                    $this->distributeStock($lot, $variant, $warehouses, $initialQty, $initialCost, $totalMovements);

                    $totalLots++;
                }
            }

            $this->command->info(sprintf(
                '✅ Se crearon %d lotes con trazabilidad y %d movimientos de inventario.',
                $totalLots,
                $totalMovements,
            ));
        });
    }

    /**
     * Reparte la cantidad inicial del lote entre los almacenes y crea tanto
     * la entrada en lot_inventories (balance por lote×almacén) como la entrada
     * en el kardex (inventories) para mantener consistencia.
     *
     * @param  Collection<int, Warehouse>  $warehouses
     */
    private function distributeStock(
        Lot $lot,
        ProductProduct $variant,
        Collection $warehouses,
        float $totalQty,
        float $costPerUnit,
        int &$totalMovements,
    ): void {
        /** @var array<int, array{0: Warehouse, 1: float}> $distribution */
        $distribution = [];

        $primary = $warehouses->first();
        if (! $primary instanceof Warehouse) {
            return;
        }

        if ($warehouses->count() === 1) {
            $distribution[] = [$primary, $totalQty];
        } else {
            $secondary = $warehouses->get(1);
            if (! $secondary instanceof Warehouse) {
                $distribution[] = [$primary, $totalQty];
            } else {
                $primaryQty = floor($totalQty * 0.7);
                $secondaryQty = $totalQty - $primaryQty;
                $distribution[] = [$primary, $primaryQty];
                $distribution[] = [$secondary, $secondaryQty];
            }
        }

        foreach ($distribution as [$warehouse, $qty]) {
            if ($qty <= 0) {
                continue;
            }

            // lot_inventories (balance por lote × warehouse)
            LotInventory::create([
                'lot_id' => $lot->id,
                'warehouse_id' => $warehouse->id,
                'quantity_balance' => $qty,
            ]);

            // Kardex (inventories) — entrada que respeta el lot_id
            $this->appendKardexEntry($variant, $warehouse, $lot, $qty, $costPerUnit);
            $totalMovements++;
        }
    }

    /**
     * Agrega una fila al kardex recalculando el balance acumulado del producto
     * en el almacén (no sólo del lote), para mantener coherencia con el resto
     * del sistema que lee el último movimiento vía product×warehouse.
     *
     * Usa el Lot como inventoryable morph (el movimiento se originó por la
     * creación inicial de stock del lote vía seeder).
     */
    private function appendKardexEntry(
        ProductProduct $variant,
        Warehouse $warehouse,
        Lot $lot,
        float $qty,
        float $costPerUnit,
    ): void {
        $subtotal = round($qty * $costPerUnit, 4);

        $last = Inventory::query()
            ->where('product_product_id', $variant->id)
            ->where('warehouse_id', $warehouse->id)
            ->latest('id')
            ->first();

        $prevBalance = $last ? (float) $last->quantity_balance : 0.0;
        $prevTotalBalance = $last ? (float) $last->total_balance : 0.0;

        $newBalance = $prevBalance + $qty;
        $newTotalBalance = $prevTotalBalance + $subtotal;
        $newCostBalance = $newBalance > 0 ? round($newTotalBalance / $newBalance, 4) : 0.0;

        Inventory::create([
            'detail' => "Stock inicial lote {$lot->lot_number}",
            'quantity_in' => $qty,
            'cost_in' => $costPerUnit,
            'total_in' => $subtotal,
            'quantity_out' => 0,
            'cost_out' => 0,
            'total_out' => 0,
            'quantity_balance' => $newBalance,
            'cost_balance' => $newCostBalance,
            'total_balance' => $newTotalBalance,
            'product_product_id' => $variant->id,
            'warehouse_id' => $warehouse->id,
            'lot_id' => $lot->id,
            'inventoryable_type' => Lot::class,
            'inventoryable_id' => $lot->id,
        ]);
    }
}
