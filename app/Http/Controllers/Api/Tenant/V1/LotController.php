<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\LotRequest;
use App\Http\Resources\LotResource;
use App\Models\Lot;
use App\Models\ProductProduct;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class LotController extends ApiController
{
    /**
     * @var array<int, string>
     */
    private const LOT_LOAD_RELATIONS = [
        'productProduct.template',
        'supplier',
        'purchase',
        'lotInventories.warehouse',
    ];

    /**
     * Listar lotes (paginado) con filtros.
     *
     * Query params:
     *   search                : busca en lot_number, sku, product name
     *   status                : active|blocked|expired|depleted|all
     *   product_product_id    : filtrar por variante
     *   warehouse_id          : con stock en almacén
     *   expiring_in_days      : próximos a vencer (entero)
     *   expired               : 1 → solo vencidos
     *   in_stock              : 1 → solo lotes con stock > 0
     *   from / to             : rango por expires_at
     *   per_page              : número | "total"
     */
    public function index(Request $request): JsonResponse
    {
        $query = Lot::query()
            ->companyFiltered()
            ->with(self::LOT_LOAD_RELATIONS)
            ->orderBy('expires_at', 'asc')
            ->orderBy('id', 'desc');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('lot_number', 'like', "%{$search}%")
                    ->orWhereHas('productProduct', function ($pq) use ($search) {
                        $pq->where('sku', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhereHas('template', function ($tq) use ($search) {
                                $tq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $status = $request->query('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($productId = $request->integer('product_product_id')) {
            $query->where('product_product_id', $productId);
        }

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->whereHas('lotInventories', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->where('quantity_balance', '>', 0);
            });
        }

        if ($expiringDays = $request->integer('expiring_in_days')) {
            $query->whereNotNull('expires_at')
                ->whereDate('expires_at', '>=', now())
                ->whereDate('expires_at', '<=', now()->addDays($expiringDays));
        }

        if ($request->boolean('expired')) {
            $query->whereNotNull('expires_at')->whereDate('expires_at', '<', now());
        }

        if ($request->boolean('in_stock')) {
            $query->whereHas('lotInventories', fn ($q) => $q->where('quantity_balance', '>', 0));
        }

        if ($from = $request->query('from')) {
            $query->whereDate('expires_at', '>=', Carbon::parse($from));
        }

        if ($to = $request->query('to')) {
            $query->whereDate('expires_at', '<=', Carbon::parse($to));
        }

        $perPage = $request->query('per_page', 25);
        if ($perPage === 'total') {
            return $this->success(LotResource::collection($query->get()));
        }

        $paginator = $query->paginate((int) $perPage)->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Lots listing retrieved successfully',
            'data' => LotResource::collection($paginator->items()),
            'links' => $paginator->linkCollection(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    /**
     * Ver un lote.
     */
    public function show(Lot $lot): JsonResponse
    {
        return $this->success(new LotResource($lot->load(self::LOT_LOAD_RELATIONS)));
    }

    /**
     * Editar metadata del lote.
     *
     * Restringido a usuarios con rol 'admin' o 'inventory_manager'.
     * Campos inmutables: product_product_id, company_id, purchase_id,
     * supplier_id, initial_quantity, initial_cost.
     */
    public function update(LotRequest $request, Lot $lot): JsonResponse
    {
        if (! $this->canManageLots($request)) {
            return $this->forbidden('No tiene permisos para editar lotes.');
        }

        $data = $request->validated();

        DB::transaction(function () use ($lot, $data) {
            $lot->update($data);
        });

        return $this->success(
            new LotResource($lot->fresh()->load(self::LOT_LOAD_RELATIONS)),
            'Lot updated successfully'
        );
    }

    /**
     * Eliminar un lote (soft-delete). Solo admin/inventory_manager.
     * Bloqueado si tiene stock o movimientos asociados.
     */
    public function destroy(Request $request, Lot $lot): JsonResponse
    {
        if (! $this->canManageLots($request)) {
            return $this->forbidden('No tiene permisos para eliminar lotes.');
        }

        $hasStock = $lot->lotInventories()->where('quantity_balance', '>', 0)->exists();
        if ($hasStock) {
            return $this->error('No se puede eliminar un lote con stock disponible.', 422);
        }

        $hasMovements = $lot->inventories()->exists();
        if ($hasMovements) {
            return $this->error('No se puede eliminar un lote con movimientos en kardex. Use "bloquear" en su lugar.', 422);
        }

        $lot->delete();

        return $this->noContent();
    }

    /**
     * Cambiar estado active ↔ blocked. Solo admin/inventory_manager.
     */
    public function toggleStatus(Request $request, Lot $lot): JsonResponse
    {
        if (! $this->canManageLots($request)) {
            return $this->forbidden('No tiene permisos para cambiar el estado del lote.');
        }

        if (! in_array($lot->status, ['active', 'blocked'], true)) {
            return $this->error("No se puede alternar el estado desde '{$lot->status}'.", 422);
        }

        $lot->status = $lot->status === 'active' ? 'blocked' : 'active';
        $lot->save();

        return $this->success(
            new LotResource($lot->fresh()->load(self::LOT_LOAD_RELATIONS)),
            'Lot status toggled successfully'
        );
    }

    /**
     * Lotes próximos a vencer.
     * GET /lots/expiring?days=30
     */
    public function expiring(Request $request): JsonResponse
    {
        $days = $request->integer('days', 30);

        $lots = Lot::query()
            ->companyFiltered()
            ->with(self::LOT_LOAD_RELATIONS)
            ->where('status', 'active')
            ->expiring($days)
            ->orderBy('expires_at', 'asc')
            ->get();

        return $this->success(LotResource::collection($lots));
    }

    /**
     * Lotes vencidos.
     * GET /lots/expired
     */
    public function expired(Request $request): JsonResponse
    {
        $lots = Lot::query()
            ->companyFiltered()
            ->with(self::LOT_LOAD_RELATIONS)
            ->expired()
            ->orderBy('expires_at', 'asc')
            ->get();

        return $this->success(LotResource::collection($lots));
    }

    /**
     * Lotes disponibles para una variante (FEFO ordered, con stock > 0).
     * GET /product-products/{productProduct}/available-lots?warehouse_id=X
     *
     * Para uso del POS / formularios de salida.
     */
    public function availableForProduct(Request $request, ProductProduct $productProduct): JsonResponse
    {
        $warehouseId = $request->integer('warehouse_id');

        $query = Lot::query()
            ->companyFiltered()
            ->with(['lotInventories' => function ($q) use ($warehouseId) {
                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            }, 'lotInventories.warehouse'])
            ->where('product_product_id', $productProduct->id)
            ->where('status', 'active')
            ->fefo();

        if ($warehouseId) {
            $query->whereHas('lotInventories', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId)
                    ->where('quantity_balance', '>', 0);
            });
        } else {
            $query->whereHas('lotInventories', fn ($q) => $q->where('quantity_balance', '>', 0));
        }

        $lots = $query->get();

        return $this->success(LotResource::collection($lots));
    }

    /**
     * Opciones para formularios/filtros de lotes.
     */
    public function formOptions(): JsonResponse
    {
        $statuses = [
            ['value' => 'active', 'label' => 'Activo'],
            ['value' => 'blocked', 'label' => 'Bloqueado'],
            ['value' => 'expired', 'label' => 'Vencido'],
            ['value' => 'depleted', 'label' => 'Agotado'],
        ];

        $warehouses = \App\Models\Warehouse::query()
            ->companyFiltered()
            ->where('is_active', true)
            ->get(['id', 'name']);

        return $this->success([
            'statuses' => $statuses,
            'warehouses' => $warehouses,
        ]);
    }

    private function canManageLots(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'manager', 'almacen']);
    }
}
