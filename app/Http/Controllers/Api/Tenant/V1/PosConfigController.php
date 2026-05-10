<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\PosConfigRequest;
use App\Http\Resources\PosConfigResource;
use App\Models\PosConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

final class PosConfigController extends ApiController
{
    /**
     * Get form options for PosConfig
     */
    public function formOptions(): JsonResponse
    {
        $warehouses = \App\Models\Warehouse::query()->companyFiltered()->latest()->get();

        $customers = \App\Models\Partner::query()
            ->companyFiltered()
            ->customers()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $taxes = [];
        if (class_exists(\App\Models\Tax::class)) {
            $taxes = \App\Models\Tax::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get();
        }

        $journals = \App\Models\Journal::query()
            ->whereIn('type', ['sale', 'cash', 'bank', 'sale_refund'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Form options retrieved successfully',
            'data' => [
                'warehouses' => \App\Http\Resources\WarehouseResource::collection($warehouses),
                'customers' => \App\Http\Resources\CustomerResource::collection($customers),
                'taxes' => $taxes,
                'journals' => $journals->map(fn($j) => [
                    'id' => $j->id,
                    'name' => $j->name,
                    'code' => $j->code,
                    'type' => $j->type,
                    'document_type_code' => $j->document_type_code,
                    'affects_document_type_code' => $j->affects_document_type_code,
                ]),
            ]
        ]);
    }

    /**
     * Listar Terminales POS
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive,all',
            'per_page' => 'nullable',
        ]);

        $query = PosConfig::query()
            ->with(['warehouse', 'tax']) // Shallow relations for grid
            ->withExists(['sessions as has_active_session' => function ($q) {
                $q->whereIn('status', ['opened', 'opening_control', 'closing_control']);
            }])
            ->orderBy('id', 'desc');

        $search = $validated['search'] ?? $validated['q'] ?? null;

        if (! empty($search)) {
            $query->where('name', 'like', "%{$search}%");
        }

        if (! empty($validated['status']) && $validated['status'] !== 'all') {
            $query->where('is_active', $validated['status'] === 'active');
        }

        $perPage = $validated['per_page'] ?? 25;

        if ($perPage === 'total') {
            $perPage = max($query->count(), 1);
        }

        $paginator = $query->paginate($perPage);

        return PosConfigResource::collection($paginator);
    }

    /**
     * Crear Terminal POS
     */
    public function store(PosConfigRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $journalsData = $validated['journals'] ?? [];

        // Remove journals from payload before creating parent
        unset($validated['journals']);

        // Default to current tenant's company if none specified directly
        if (! isset($validated['company_id'])) {
            $validated['company_id'] = $request->user()?->company_id ?? 1;
        }

        // Translate the simple array input array into what Eloquent sync() needs
        $syncData = [];
        foreach ($journalsData as $journalEntry) {
            $journalId = $journalEntry['journal_id'];
            $syncData[$journalId] = [
                'document_type' => $journalEntry['document_type'],
                'is_default' => $journalEntry['is_default'] ?? false,
            ];
        }

        $posConfig = DB::transaction(function () use ($validated, $syncData) {
            $config = PosConfig::create($validated);

            // Sync Journals logic
            $config->journals()->sync($syncData);

            return $config->loadMissing(['warehouse', 'defaultCustomer', 'tax', 'journals']);
        });

        return response()->json([
            'message' => 'Terminal POS creada exitosamente.',
            'data' => new PosConfigResource($posConfig),
        ], 201);
    }

    /**
     * Ver Terminal POS
     */
    public function show(PosConfig $posConfig): PosConfigResource
    {
        return new PosConfigResource($posConfig->loadMissing(['warehouse', 'defaultCustomer', 'tax', 'journals']));
    }

    /**
     * Actualizar Terminal POS
     */
    public function update(PosConfigRequest $request, PosConfig $posConfig): JsonResponse
    {
        $validated = $request->validated();
        $journalsData = $validated['journals'] ?? null;

        if (isset($validated['journals'])) {
            unset($validated['journals']);
        }

        $posConfig = DB::transaction(function () use ($posConfig, $validated, $journalsData) {
            $posConfig->update($validated);

            if ($journalsData !== null) {
                // Translate the simple array input array into what Eloquent sync() needs
                $syncData = [];
                foreach ($journalsData as $journalEntry) {
                    $journalId = $journalEntry['journal_id'];
                    $syncData[$journalId] = [
                        'document_type' => $journalEntry['document_type'],
                        'is_default' => $journalEntry['is_default'] ?? false,
                    ];
                }

                // Wipe and recreate relationships with fresh pivot properties
                $posConfig->journals()->sync($syncData);
            }

            return $posConfig->refresh()->loadMissing(['warehouse', 'defaultCustomer', 'tax', 'journals']);
        });

        return response()->json([
            'message' => 'Configuración actualizada exitosamente.',
            'data' => new PosConfigResource($posConfig),
        ]);
    }

    /**
     * Eliminar Terminal POS
     */
    public function destroy(PosConfig $posConfig): JsonResponse
    {
        // Need to detach any pivot relations
        DB::transaction(function () use ($posConfig) {
            $posConfig->journals()->detach();
            $posConfig->delete();
        });

        return response()->json([
            'message' => 'Terminal POS eliminada exitosamente.',
        ]);
    }

    /**
     * Cambiar Estado
     */
    public function toggleStatus(PosConfig $posConfig): JsonResponse
    {
        $posConfig->update([
            'is_active' => ! $posConfig->is_active,
        ]);

        return $this->success(new PosConfigResource($posConfig->fresh()->loadMissing(['warehouse', 'tax'])));
    }
}
