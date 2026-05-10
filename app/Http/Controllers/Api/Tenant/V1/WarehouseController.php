<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\WarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WarehouseController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 25);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Warehouse::query()->companyFiltered()->with(['company']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } else if ($status === 'all') {
            // Include both
        } else {
            // Default to active only
            $query->where('is_active', true);
        }

        $warehouses = $query->latest()->paginate((int) $perPage)->appends($request->query());

        return $this->success(
            WarehouseResource::collection($warehouses)->response()->getData(true)
        );
    }

    public function formOptions(): JsonResponse
    {
        $companies = \App\Models\Company::query()->orderBy('business_name')->get();

        return $this->success([
            'companies' => \App\Http\Resources\CompanyResource::collection($companies),
        ], 'Form options retrieved successfully');
    }

    public function store(WarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());
        $warehouse->load('company');

        return $this->created(new WarehouseResource($warehouse));
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load('company');

        return $this->success(new WarehouseResource($warehouse));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());
        $warehouse->load('company');

        return $this->success(new WarehouseResource($warehouse));
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Warehouse::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggleStatus(Warehouse $warehouse): JsonResponse
    {
        $warehouse->update(['is_active' => !$warehouse->is_active]);
        
        $warehouse->load('company');

        return $this->success(new WarehouseResource($warehouse));
    }
}
