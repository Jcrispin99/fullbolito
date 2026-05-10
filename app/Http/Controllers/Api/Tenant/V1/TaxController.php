<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\TaxRequest;
use App\Http\Resources\TaxResource;
use App\Models\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TaxController extends ApiController
{
    /**
     * Provide active taxes directly for form selects.
     */
    public function formOptions(): JsonResponse
    {
        $taxes = Tax::active()->orderBy('name')->get();

        return $this->success([
            'taxes' => TaxResource::collection($taxes),
        ]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Tax::orderBy('is_default', 'desc')
            ->orderBy('is_active', 'desc')
            ->orderBy('name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('tax_type', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } elseif ($status === 'all') {
            // Include both
        } else {
            // Default: only active
            $query->where('is_active', true);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            $taxes = $query->get();
            return $this->success(TaxResource::collection($taxes));
        }

        $taxes = $query->paginate((int) $perPage);

        return $this->success(
            TaxResource::collection($taxes)->response()->getData(true)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TaxRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tax = DB::transaction(function () use ($validated) {
            if ($validated['is_default'] ?? false) {
                Tax::where('tax_type', $validated['tax_type'])
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return Tax::create($validated);
        });

        return $this->created(new TaxResource($tax));
    }

    /**
     * Display the specified resource.
     */
    public function show(Tax $tax): JsonResponse
    {
        return $this->success(new TaxResource($tax));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(TaxRequest $request, Tax $tax): JsonResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $tax) {
            if (isset($validated['is_default']) && $validated['is_default'] === true) {
                $taxType = $validated['tax_type'] ?? $tax->tax_type;

                Tax::where('tax_type', $taxType)
                    ->where('is_default', true)
                    ->where('id', '!=', $tax->id)
                    ->update(['is_default' => false]);
            }

            $tax->update($validated);
        });

        return $this->success(new TaxResource($tax->refresh()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Tax $tax): JsonResponse
    {
        if ($tax->productables()->count() > 0) {
            return $this->error('No se puede eliminar el impuesto porque está siendo actualmente usado en productos documentados.', 422);
        }

        $tax->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Tax::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle tax `is_active` status flag
     */
    public function toggleStatus(Tax $tax): JsonResponse
    {
        $tax->update([
            'is_active' => ! $tax->is_active,
        ]);

        return $this->success(new TaxResource($tax->refresh()));
    }
}
