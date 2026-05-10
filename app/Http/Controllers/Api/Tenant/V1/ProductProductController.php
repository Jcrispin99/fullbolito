<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\ProductProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ProductProductController extends ApiController
{
    public function posCatalog(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $categoryId = $request->integer('category_id');
        $warehouseId = $request->integer('warehouse_id');
        $limit = (int) $request->input('limit', 200);

        $query = ProductProduct::query()
            ->with(['template.category', 'template.uom', 'template.mainImage', 'attributeValues.attribute'])
            ->whereHas('template', function ($q) {
                $q->where('is_active', true)
                    ->where('is_pos_visible', true);
            })
            ->where(fn ($q) => $this->excludeOrphanPrincipal($q))
            ->orderByDesc('is_principal')
            ->orderBy('id');

        if ($categoryId > 0) {
            $query->whereHas('template', function ($q) use ($categoryId) {
                $q->where('category_id', $categoryId);
            });
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('template', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $query->take($limit)->get();

        $formatted = $products->map(function (ProductProduct $product) use ($warehouseId) {
            $template = $product->template;
            $uom = $template?->uom;
            $category = $template?->category;
            $stock = $warehouseId ? $product->getStockInWarehouse($warehouseId) : $product->stock;

            return [
                'id' => $product->id,
                'product_template_id' => $product->product_template_id,
                'display_name' => $product->display_name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'price' => (float) ($product->price ?? $template?->price ?? 0),
                'cost_price' => (float) $product->cost_price,
                'stock' => (int) $stock,
                'category_id' => $category?->id,
                'category_name' => $category?->name,
                'image_url' => $template?->image,
                'uom_id' => $uom?->id,
                'uom_name' => $uom?->name,
                'is_pos_visible' => (bool) ($template?->is_pos_visible ?? false),
                'tracks_inventory' => (bool) ($template?->tracks_inventory ?? true),
                'is_service' => (bool) ($template?->is_service ?? false),
                'is_tracked_by_lot' => (bool) ($template?->tracked_by_lot ?? false),
            ];
        });

        return $this->success($formatted, 'POS products retrieved successfully');
    }

    public function search(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $limit = $request->input('limit', 20);

        $query = ProductProduct::with(['template', 'attributeValues.attribute'])
            ->whereHas('template', function ($q) {
                $q->where('is_active', true);
            })
            ->where(fn ($q) => $this->excludeOrphanPrincipal($q));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhereHas('template', function ($tq) use ($search) {
                        $tq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $query->take((int) $limit)->get();

        $formatted = $products->map(function ($product) {
            $template = $product->template;
            $uom = $template?->uom;
            return [
                'id' => $product->id,
                'product_template_id' => $product->product_template_id,
                'display_name' => $product->display_name,
                'sku' => $product->sku,
                'price' => $product->cost_price, // Return cost_price for purchases
                'uom_id' => $uom ? $uom->id : null,
                'uom_name' => $uom ? $uom->name : null,
                'is_tracked_by_lot' => (bool) ($template?->tracked_by_lot ?? false),
            ];
        });

        return $this->success($formatted, 'Products retrieved successfully');
    }

    /**
     * Restrict the query to variants that should be selectable in user-facing
     * pickers. Hides the "principal orphan" — a variant without attribute values
     * that exists only because the template was originally created without
     * attributes — when its template already has at least one attributed variant.
     */
    private function excludeOrphanPrincipal($query): void
    {
        $query->where(function ($q) {
            $q->whereHas('attributeValues')
                ->orWhereNotIn('product_template_id', function ($sub) {
                    $sub->select('product_template_id')
                        ->from('product_products as pp_attr')
                        ->whereExists(function ($inner) {
                            $inner->select(DB::raw(1))
                                ->from('attribute_value_products as avp')
                                ->whereColumn('avp.product_product_id', 'pp_attr.id');
                        });
                });
        });
    }
}
