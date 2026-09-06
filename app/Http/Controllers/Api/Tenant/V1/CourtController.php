<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\CourtRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\CourtResource;
use App\Models\Category;
use App\Models\Company;
use App\Models\Court;
use App\Models\ProductProduct;
use App\Models\ProductTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CourtController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');
        $status = $request->input('status');
        $sport = $request->input('sport');
        $companyId = $request->input('company_id');

        $query = Court::query()
            ->companyFiltered()
            ->with(['company', 'productProduct']);

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($sport) {
            $query->where('sport', $sport);
        }

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include both
        } else {
            $query->where('is_active', true);
        }

        $courts = $query->latest()->paginate((int) $perPage)->appends($request->query());

        return $this->success(
            CourtResource::collection($courts)->response()->getData(true)
        );
    }

    public function formOptions(): JsonResponse
    {
        $companies = Company::query()->orderBy('business_name')->get();

        return $this->success([
            'companies' => CompanyResource::collection($companies),
        ], 'Form options retrieved successfully');
    }

    public function store(CourtRequest $request): JsonResponse
    {
        $data = $request->validated();
        $price = (float) $data['price'];
        $companyId = (int) $data['company_id'];

        $court = DB::transaction(function () use ($data, $price, $companyId) {
            $template = ProductTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $price,
                'category_id' => $this->resolveCanchasCategoryId(),
                'is_active' => true,
                // Es servicio: no consume stock, no aparece en POS.
                'is_service' => true,
                'tracks_inventory' => false,
                'is_pos_visible' => false,
                'tracked_by_lot' => false,
            ]);

            $product = ProductProduct::create([
                'product_template_id' => $template->id,
                'sku' => $this->generateSku(),
                'price' => $price,
                'cost_price' => 0,
                'is_principal' => true,
            ]);

            return Court::create([
                'name' => $data['name'],
                'slug' => $this->resolveSlug($data['slug'] ?? null, $data['name'], $companyId),
                'code' => $data['code'] ?? null,
                'description' => $data['description'] ?? null,
                'sport' => $data['sport'],
                'surface' => $data['surface'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'slot_duration_minutes' => $data['slot_duration_minutes'] ?? 60,
                'company_id' => $companyId,
                'product_product_id' => $product->id,
                'is_active' => $data['is_active'] ?? true,
            ]);
        });

        $court->load(['company', 'productProduct']);

        return $this->created(new CourtResource($court));
    }

    public function show(Court $court): JsonResponse
    {
        $court->load(['company', 'productProduct']);

        return $this->success(new CourtResource($court));
    }

    public function update(CourtRequest $request, Court $court): JsonResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $court): void {
            // Sincroniza nombre/precio al producto facturable oculto.
            $product = $court->productProduct;
            if ($product) {
                $productUpdates = [];
                if (array_key_exists('price', $data)) {
                    $productUpdates['price'] = (float) $data['price'];
                }
                if (! empty($productUpdates)) {
                    $product->update($productUpdates);
                }

                $template = $product->productTemplate;
                if ($template) {
                    $templateUpdates = [];
                    if (array_key_exists('name', $data)) {
                        $templateUpdates['name'] = $data['name'];
                    }
                    if (array_key_exists('description', $data)) {
                        $templateUpdates['description'] = $data['description'];
                    }
                    if (array_key_exists('price', $data)) {
                        $templateUpdates['price'] = (float) $data['price'];
                    }
                    if (! empty($templateUpdates)) {
                        $template->update($templateUpdates);
                    }
                }
            }

            // Slug se preserva al renombrar (URLs públicas estables); para
            // forzar nuevo slug, mandar 'slug' explícito.
            $courtUpdates = $data;
            unset($courtUpdates['price']);
            if (array_key_exists('slug', $data)) {
                $courtUpdates['slug'] = $this->resolveSlug(
                    $data['slug'],
                    $data['name'] ?? $court->name,
                    $data['company_id'] ?? $court->company_id,
                    $court->id,
                );
            }

            $court->update($courtUpdates);
        });

        $court->load(['company', 'productProduct']);

        return $this->success(new CourtResource($court));
    }

    public function destroy(Court $court): JsonResponse
    {
        DB::transaction(function () use ($court): void {
            // Desactiva el producto facturable oculto (queda referenciado por
            // ventas históricas, no se puede borrar).
            if ($product = $court->productProduct) {
                $product->update(['price' => $product->price]);
                if ($template = $product->productTemplate) {
                    $template->update(['is_active' => false]);
                }
            }

            $court->delete();
        });

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);

        DB::transaction(function () use ($request): void {
            $courts = Court::query()->whereIn('id', $request->ids)->with('productProduct.productTemplate')->get();
            foreach ($courts as $court) {
                if ($template = $court->productProduct?->productTemplate) {
                    $template->update(['is_active' => false]);
                }
                $court->delete();
            }
        });

        return $this->noContent();
    }

    public function toggleStatus(Court $court): JsonResponse
    {
        $court->update(['is_active' => ! $court->is_active]);
        $court->load(['company', 'productProduct']);

        return $this->success(new CourtResource($court));
    }

    /**
     * Encuentra (o crea) la categoría usada para los servicios de canchas.
     * Reusa la categoría "Deportes y Aire Libre" si existe; si no la crea.
     */
    private function resolveCanchasCategoryId(): int
    {
        $existing = Category::query()
            ->where('name', 'Deportes y Aire Libre')
            ->orWhere('name', 'Canchas')
            ->orWhere('name', 'Servicios')
            ->first();

        if ($existing) {
            return $existing->id;
        }

        return Category::create([
            'name' => 'Canchas',
            'is_active' => true,
        ])->id;
    }

    private function generateSku(): string
    {
        do {
            $candidate = 'COURT-' . Str::upper(Str::random(8));
        } while (ProductProduct::query()->where('sku', $candidate)->exists());

        return $candidate;
    }

    private function resolveSlug(?string $slug, string $name, ?int $companyId, ?int $ignoreId = null): string
    {
        $base = $slug ? Str::slug($slug) : Str::slug($name);
        $candidate = $base;
        $i = 2;

        while (Court::query()
            ->where('company_id', $companyId)
            ->where('slug', $candidate)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $candidate = "{$base}-{$i}";
            $i++;
        }

        return $candidate;
    }
}
