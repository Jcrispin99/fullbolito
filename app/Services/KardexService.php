<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Inventory;
use App\Models\Lot;
use App\Models\LotInventory;
use App\Models\ProductProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class KardexService
{
    /**
     * Obtiene el último registro de inventario para una variante en un almacén.
     * Si se pasa $lotId, filtra por ese lote específico.
     *
     * @return array<string, mixed>
     */
    public function getLastRecord(int $productProductId, int $warehouseId, ?int $lotId = null): array
    {
        $query = Inventory::where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId);

        if ($lotId !== null) {
            $query->where('lot_id', $lotId);
        }

        $lastRecord = $query->latest()->first();

        return [
            'quantity' => $lastRecord?->quantity_balance ?? 0.0,
            'cost' => $lastRecord?->cost_balance ?? 0.0,
            'total' => $lastRecord?->total_balance ?? 0.0,
            'date' => $lastRecord?->created_at ?? null,
        ];
    }

    /**
     * Registra una entrada al inventario (compras, transferencias entrantes, etc.).
     * Si $lotId está presente, el movimiento queda atado al lote y se actualiza lot_inventories.
     *
     * @param  mixed  $model  Modelo polimórfico (Purchase, Transfer, etc.)
     * @param  array<string, mixed>  $variant  ['id', 'quantity', 'price', 'subtotal']
     */
    public function registerEntry(mixed $model, array $variant, int $warehouseId, string $detail, ?int $lotId = null): void
    {
        DB::transaction(function () use ($model, $variant, $warehouseId, $detail, $lotId) {
            $qty = (float) ($variant['quantity'] ?? 0);

            // Normalizar costo unitario: si viene "subtotal" (monto base sin IGV) y es > 0, usarlo.
            $baseSubtotal = $variant['subtotal'] ?? null;
            $unitCost = ($baseSubtotal !== null && (float) $baseSubtotal > 0 && $qty > 0)
                ? ((float) $baseSubtotal / $qty)
                : (float) ($variant['price'] ?? 0);

            // Balance global producto+almacén (mantiene compatibilidad para productos sin lote)
            $lastRecord = $this->getLastRecord((int) $variant['id'], $warehouseId);
            $newQuantityBalance = $lastRecord['quantity'] + $qty;
            $newTotalBalance = $lastRecord['total'] + ($qty * $unitCost);
            $newCostBalance = $newTotalBalance / ($newQuantityBalance ?: 1);

            $model->inventories()->create([
                'detail' => $detail,
                'quantity_in' => $qty,
                'cost_in' => $unitCost,
                'total_in' => $qty * $unitCost,
                'quantity_out' => 0.0,
                'cost_out' => 0.0,
                'total_out' => 0.0,
                'quantity_balance' => $newQuantityBalance,
                'cost_balance' => $newCostBalance,
                'total_balance' => $newTotalBalance,
                'product_product_id' => $variant['id'],
                'warehouse_id' => $warehouseId,
                'lot_id' => $lotId,
            ]);

            if ($lotId !== null) {
                $this->incrementLotInventory($lotId, $warehouseId, $qty);
            }
        });
    }

    /**
     * Registra salida del inventario.
     *
     * - Si $lotAllocations viene, se consume exactamente así (selección manual del POS/venta).
     * - Si es null y el producto lleva lote, aplica FEFO automático.
     * - Si es null y el producto NO lleva lote, comportamiento clásico (promedio ponderado).
     *
     * Devuelve las asignaciones efectivas usadas (vacío si el producto no lleva lote).
     *
     * @param  array<string, mixed>  $variant  ['id', 'quantity']
     * @param  array<int, array{lot_id: int, quantity: float}>|null  $lotAllocations
     * @return array<int, array{lot_id: int, quantity: float}>
     */
    public function registerExit(mixed $model, array $variant, int $warehouseId, string $detail, ?array $lotAllocations = null, bool $allowNegativeStock = false): array
    {
        return DB::transaction(function () use ($model, $variant, $warehouseId, $detail, $lotAllocations, $allowNegativeStock) {
            $productProductId = (int) $variant['id'];
            $totalQty = (float) ($variant['quantity'] ?? 0);

            $isTracked = $this->isLotTracked($productProductId);

            // Productos sin lote → flujo clásico por promedio ponderado
            if (! $isTracked && $lotAllocations === null) {
                $this->writeExitRow($model, $productProductId, $warehouseId, $totalQty, $detail, null, $allowNegativeStock);

                return [];
            }

            // Resolver allocations: o vienen explícitas, o se calculan FEFO
            $allocations = $lotAllocations ?? $this->allocateFefo($productProductId, $warehouseId, $totalQty, false, $allowNegativeStock);

            $allocatedTotal = array_sum(array_map(fn ($a) => (float) $a['quantity'], $allocations));
            $remainder = $totalQty - $allocatedTotal;

            if (abs($remainder) > 0.0001) {
                if (! $allowNegativeStock) {
                    throw new RuntimeException("La suma de allocations ({$allocatedTotal}) no coincide con la cantidad solicitada ({$totalQty}).");
                }

                // Cantidad no cubierta por lotes disponibles → se registra como salida sin lote.
                // Permite mantener el ledger de inventario con balance negativo para el producto.
                $this->writeExitRow($model, $productProductId, $warehouseId, $remainder, $detail, null, true);
            }

            foreach ($allocations as $alloc) {
                $this->writeExitRow(
                    $model,
                    $productProductId,
                    $warehouseId,
                    (float) $alloc['quantity'],
                    $detail,
                    (int) $alloc['lot_id'],
                    $allowNegativeStock,
                );
            }

            // Normalizar para retorno (sólo lot_id + quantity)
            return array_map(
                fn ($a) => ['lot_id' => (int) $a['lot_id'], 'quantity' => (float) $a['quantity']],
                $allocations,
            );
        });
    }

    /**
     * Devuelve las cantidades por lote ya consumidas por un modelo (Sale/POS) en un almacén
     * para una variante específica. Útil al cancelar para devolver stock al lote correcto.
     *
     * @return array<int, array{lot_id: int, quantity: float}>  (sólo entradas con lot_id no nulo)
     */
    public function getExitAllocationsForModel(mixed $model, int $productProductId, int $warehouseId): array
    {
        $rows = Inventory::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_out', '>', 0)
            ->whereNotNull('lot_id')
            ->selectRaw('lot_id, SUM(quantity_out) as qty')
            ->groupBy('lot_id')
            ->get();

        return $rows->map(fn ($r) => [
            'lot_id' => (int) $r->lot_id,
            'quantity' => (float) $r->qty,
        ])->all();
    }

    /**
     * Devuelve la cantidad total registrada como salida SIN lote para un modelo+producto+almacén.
     * Útil para ventas POS con allowNegativeStock que escribieron un remanente sin lote.
     */
    public function getNoLotExitQuantityForModel(mixed $model, int $productProductId, int $warehouseId): float
    {
        return (float) Inventory::query()
            ->where('inventoryable_type', $model->getMorphClass())
            ->where('inventoryable_id', $model->getKey())
            ->where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId)
            ->where('quantity_out', '>', 0)
            ->whereNull('lot_id')
            ->sum('quantity_out');
    }

    /**
     * Registra un ajuste de inventario (corrección de stock manual).
     * Para productos con lote, $lotId es obligatorio.
     *
     * @param  array<string, mixed>  $variant  ['id', 'quantity', 'reason']
     */
    public function registerAdjustment(mixed $model, array $variant, int $warehouseId, string $detail, ?int $lotId = null): void
    {
        $qty = (float) ($variant['quantity'] ?? 0);

        if ($qty >= 0) {
            $this->registerEntry($model, $variant, $warehouseId, $detail, $lotId);
        } else {
            $variant['quantity'] = abs($qty);
            $allocations = $lotId !== null
                ? [['lot_id' => $lotId, 'quantity' => abs($qty)]]
                : null;
            $this->registerExit($model, $variant, $warehouseId, $detail, $allocations);
        }
    }

    /**
     * Kardex completo de un producto en un almacén (opcionalmente filtrado por lote).
     *
     * @return Collection<int, Inventory>
     */
    public function getKardex(int $productProductId, int $warehouseId, ?int $lotId = null): Collection
    {
        $query = Inventory::where('product_product_id', $productProductId)
            ->where('warehouse_id', $warehouseId);

        if ($lotId !== null) {
            $query->where('lot_id', $lotId);
        }

        return $query->with(['inventoryable', 'warehouse', 'lot'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    /**
     * Stock actual agregado de un producto en un almacén.
     * Para productos con lote, suma los saldos de todos sus lotes.
     */
    public function getCurrentStock(int $productProductId, int $warehouseId, ?int $lotId = null): float
    {
        if ($lotId !== null) {
            $li = LotInventory::where('lot_id', $lotId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            return (float) ($li?->quantity_balance ?? 0);
        }

        if ($this->isLotTracked($productProductId)) {
            return (float) LotInventory::whereHas('lot', fn ($q) => $q->where('product_product_id', $productProductId))
                ->where('warehouse_id', $warehouseId)
                ->sum('quantity_balance');
        }

        $lastRecord = $this->getLastRecord($productProductId, $warehouseId);

        return (float) $lastRecord['quantity'];
    }

    public function hasEnoughStock(int $productProductId, int $warehouseId, float $requiredQuantity): bool
    {
        return $this->getCurrentStock($productProductId, $warehouseId) >= $requiredQuantity;
    }

    /**
     * Lista lotes disponibles (stock > 0) en un almacén, ordenados FEFO.
     *
     * @return Collection<int, Lot>
     */
    public function getAvailableLots(int $productProductId, int $warehouseId, bool $includeExpired = false): Collection
    {
        $query = Lot::query()
            ->forProduct($productProductId)
            ->active()
            ->whereHas('lotInventories', fn ($q) => $q
                ->where('warehouse_id', $warehouseId)
                ->where('quantity_balance', '>', 0))
            ->with(['lotInventories' => fn ($q) => $q->where('warehouse_id', $warehouseId)])
            ->fefo();

        if (! $includeExpired) {
            $query->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhereDate('expires_at', '>=', now());
            });
        }

        return $query->get();
    }

    /**
     * Calcula la distribución FEFO para cubrir una cantidad requerida.
     *
     * @return array<int, array{lot_id: int, quantity: float, unit_cost: float}>
     *
     * @throws RuntimeException si no hay stock suficiente
     */
    public function allocateFefo(int $productProductId, int $warehouseId, float $requiredQuantity, bool $allowExpired = false, bool $allowPartial = false): array
    {
        $available = $this->getAvailableLots($productProductId, $warehouseId, $allowExpired);

        $allocations = [];
        $remaining = $requiredQuantity;

        foreach ($available as $lot) {
            if ($remaining <= 0) {
                break;
            }

            /** @var LotInventory|null $li */
            $li = $lot->lotInventories->first();
            $stock = (float) ($li?->quantity_balance ?? 0);

            if ($stock <= 0) {
                continue;
            }

            $take = min($stock, $remaining);

            $allocations[] = [
                'lot_id' => (int) $lot->id,
                'quantity' => $take,
                'unit_cost' => (float) $lot->initial_cost,
            ];

            $remaining -= $take;
        }

        if ($remaining > 0.0001 && ! $allowPartial) {
            throw new RuntimeException(
                "Stock insuficiente en el almacén {$warehouseId} para el producto {$productProductId}. Faltan {$remaining} unidades."
            );
        }

        return $allocations;
    }

    // ─── Privados ───────────────────────────────────────────────────────────

    /**
     * Escribe una fila de salida en `inventories` para un lote específico
     * (o sin lote si $lotId es null), y decrementa lot_inventories si aplica.
     */
    private function writeExitRow(mixed $model, int $productProductId, int $warehouseId, float $qty, string $detail, ?int $lotId, bool $allowNegative = false): void
    {
        // Costo unitario: si hay lote, usa su initial_cost; si no, usa el último cost_balance del producto+almacén
        if ($lotId !== null) {
            $lot = Lot::lockForUpdate()->findOrFail($lotId);
            $unitCost = (float) $lot->initial_cost;
        } else {
            $last = $this->getLastRecord($productProductId, $warehouseId);
            $unitCost = (float) $last['cost'];
        }

        // Balance global producto+almacén (suma de todos los lotes)
        $last = $this->getLastRecord($productProductId, $warehouseId);
        $newQuantityBalance = $last['quantity'] - $qty;
        $newTotalBalance = $last['total'] - ($qty * $unitCost);
        $newCostBalance = $newQuantityBalance > 0
            ? $newTotalBalance / $newQuantityBalance
            : 0;

        $model->inventories()->create([
            'detail' => $detail,
            'quantity_in' => 0.0,
            'cost_in' => 0.0,
            'total_in' => 0.0,
            'quantity_out' => $qty,
            'cost_out' => $unitCost,
            'total_out' => $qty * $unitCost,
            'quantity_balance' => $newQuantityBalance,
            'cost_balance' => $newCostBalance,
            'total_balance' => $newTotalBalance,
            'product_product_id' => $productProductId,
            'warehouse_id' => $warehouseId,
            'lot_id' => $lotId,
        ]);

        if ($lotId !== null) {
            $this->decrementLotInventory($lotId, $warehouseId, $qty, $allowNegative);
            $this->checkLotDepletion($lotId);
        }
    }

    private function incrementLotInventory(int $lotId, int $warehouseId, float $qty): void
    {
        $li = LotInventory::lockForUpdate()
            ->firstOrNew(['lot_id' => $lotId, 'warehouse_id' => $warehouseId]);

        $li->quantity_balance = (float) ($li->quantity_balance ?? 0) + $qty;
        $li->save();

        // Reactivar lote si quedó marcado como `depleted` y ahora vuelve a tener stock.
        if ($qty > 0) {
            Lot::where('id', $lotId)
                ->where('status', 'depleted')
                ->update(['status' => 'active']);
        }
    }

    private function decrementLotInventory(int $lotId, int $warehouseId, float $qty, bool $allowNegative = false): void
    {
        $li = LotInventory::where('lot_id', $lotId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        if ($li === null) {
            if (! $allowNegative) {
                throw new RuntimeException("Stock insuficiente en lote {$lotId} (almacén {$warehouseId}). Disponible: 0, requerido: {$qty}.");
            }
            $li = new LotInventory([
                'lot_id' => $lotId,
                'warehouse_id' => $warehouseId,
                'quantity_balance' => 0,
            ]);
        } elseif (! $allowNegative && (float) $li->quantity_balance < $qty - 0.0001) {
            $current = (float) ($li->quantity_balance ?? 0);
            throw new RuntimeException("Stock insuficiente en lote {$lotId} (almacén {$warehouseId}). Disponible: {$current}, requerido: {$qty}.");
        }

        $li->quantity_balance = (float) $li->quantity_balance - $qty;
        $li->save();
    }

    /**
     * Marca el lote como `depleted` si ya no tiene stock en ningún almacén.
     */
    private function checkLotDepletion(int $lotId): void
    {
        $totalStock = (float) LotInventory::where('lot_id', $lotId)->sum('quantity_balance');

        if ($totalStock <= 0) {
            Lot::where('id', $lotId)
                ->where('status', 'active')
                ->update(['status' => 'depleted']);
        }
    }

    private function isLotTracked(int $productProductId): bool
    {
        $product = ProductProduct::with('product:id,tracked_by_lot')->find($productProductId);

        return $product !== null && $product->isTrackedByLot();
    }

    /**
     * Verifica que un lote pueda ser vendido. Lanza RuntimeException si no.
     *
     * @param  bool  $allowExpired  Permitir vender lotes vencidos (override admin/POS).
     */
    public function assertLotSellable(int $lotId, bool $allowExpired = false): void
    {
        $lot = Lot::query()->find($lotId);

        if ($lot === null) {
            throw new RuntimeException("Lote {$lotId} no encontrado.");
        }

        if ($lot->status === 'blocked') {
            throw new RuntimeException("El lote {$lot->lot_number} está bloqueado y no puede venderse.");
        }

        if ($lot->status === 'depleted') {
            throw new RuntimeException("El lote {$lot->lot_number} está agotado.");
        }

        if (! $allowExpired && $lot->isExpired()) {
            throw new RuntimeException("El lote {$lot->lot_number} está vencido.");
        }
    }
}
