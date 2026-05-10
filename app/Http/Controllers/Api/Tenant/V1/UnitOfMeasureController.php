<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\UnitOfMeasureRequest;
use App\Http\Resources\UnitOfMeasureResource;
use App\Models\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UnitOfMeasureController extends ApiController
{
    /**
     * Provide lists or structured groups of families for frontend interfaces.
     */
    public function formOptions(): JsonResponse
    {
        // Provide available grouped families and independent active units
        $families = UnitOfMeasure::active()
            ->whereNotNull('family')
            ->distinct()
            ->pluck('family');

        $activeUnits = UnitOfMeasure::active()->orderBy('family')->orderBy('name')->get();

        return $this->success([
            'families' => $families,
            'unit_of_measures' => UnitOfMeasureResource::collection($activeUnits),
        ], 'Form options retrieved successfully');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = UnitOfMeasure::query()
            ->with('baseUnit')
            ->orderBy('family')
            ->orderBy('name');

        if ($search) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('symbol', 'like', "%{$search}%");
            });
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include both
        } else {
            // Default to active only
            $query->active();
        }

        if ($perPage === '-1' || $perPage === 'total') {
            $units = $query->get();

            return $this->success(UnitOfMeasureResource::collection($units));
        }

        $units = $query->paginate((int) $perPage);

        return $this->success(
            UnitOfMeasureResource::collection($units)->response()->getData(true)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UnitOfMeasureRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $isBase = empty($validated['base_unit_id']);
        $factor = $isBase ? 1 : (float) ($validated['factor'] ?? 1);

        $unitOfMeasure = UnitOfMeasure::create([
            'name' => $validated['name'],
            'symbol' => $validated['symbol'] ?? null,
            'family' => $validated['family'],
            'base_unit_id' => $isBase ? null : (int) $validated['base_unit_id'],
            'factor' => $factor,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        $unitOfMeasure->load('baseUnit');

        return $this->created(new UnitOfMeasureResource($unitOfMeasure));
    }

    /**
     * Display the specified resource.
     */
    public function show(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $unitOfMeasure->load(['baseUnit', 'derivedUnits']);

        return $this->success(new UnitOfMeasureResource($unitOfMeasure));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UnitOfMeasureRequest $request, UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('base_unit_id', $validated)) {
            $isBase = empty($validated['base_unit_id']);
            $validated['base_unit_id'] = $isBase ? null : (int) $validated['base_unit_id'];

            if (array_key_exists('factor', $validated)) {
                $validated['factor'] = $isBase ? 1 : (float) ($validated['factor'] ?? $unitOfMeasure->factor);
            } elseif ($isBase) {
                $validated['factor'] = 1;
            }
        } elseif (array_key_exists('factor', $validated)) {
            $isBase = empty($unitOfMeasure->base_unit_id);
            $validated['factor'] = $isBase ? 1 : (float) ($validated['factor'] ?? $unitOfMeasure->factor);
        }

        $unitOfMeasure->update($validated);
        $unitOfMeasure->refresh()->load(['baseUnit']);

        return $this->success(new UnitOfMeasureResource($unitOfMeasure));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        if ($unitOfMeasure->derivedUnits()->exists()) {
            return $this->error('No se puede eliminar una unidad que se utiliza actualmente como unidad base para sub-unidades derivadas.', 422);
        }

        if ($unitOfMeasure->productables()->exists()) {
            return $this->error('No se puede eliminar una unidad atada al historial de ventas o compras.', 422);
        }

        $unitOfMeasure->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        UnitOfMeasure::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle the UoM status flag.
     */
    public function toggleStatus(UnitOfMeasure $unitOfMeasure): JsonResponse
    {
        $unitOfMeasure->update([
            'is_active' => ! $unitOfMeasure->is_active,
        ]);

        return $this->success(new UnitOfMeasureResource($unitOfMeasure->load('baseUnit')));
    }
}
