<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\AttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AttributeController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Attribute::query()->withValues()->orderBy('id');

        if ($search) {
            $query->search($search);
        }

        if ($status === 'inactive' || $status === 'archived') {
            $query->where('is_active', false);
        } else if ($status === 'all') {
            // Include both
        } else {
            // Default to active only
            $query->where('is_active', true);
        }

        if ($perPage === '-1' || $perPage === 'total') {
            $attributes = $query->get();

            return $this->success(AttributeResource::collection($attributes));
        }

        $attributes = $query->paginate((int) $perPage);

        return $this->success(
            AttributeResource::collection($attributes)->response()->getData(true)
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $attribute = Attribute::create([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['values']) && is_array($data['values'])) {
            foreach ($data['values'] as $value) {
                $attribute->attributeValues()->create(['value' => $value]);
            }
        }

        $attribute->load('attributeValues');

        return $this->created(new AttributeResource($attribute));
    }

    /**
     * Display the specified resource.
     */
    public function show(Attribute $attribute): JsonResponse
    {
        $attribute->load('attributeValues');

        return $this->success(new AttributeResource($attribute));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeRequest $request, Attribute $attribute): JsonResponse
    {
        $data = $request->validated();
        
        $attribute->update([
            'name' => $data['name'] ?? $attribute->name,
            'is_active' => $data['is_active'] ?? $attribute->is_active,
        ]);

        if (isset($data['values']) && is_array($data['values'])) {
            // Re-sync values
            $attribute->attributeValues()->delete();
            foreach ($data['values'] as $value) {
                $attribute->attributeValues()->create(['value' => $value]);
            }
        }

        $attribute->load('attributeValues');

        return $this->success(new AttributeResource($attribute));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attribute $attribute): JsonResponse
    {
        $attribute->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Attribute::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggleStatus(Attribute $attribute): JsonResponse
    {
        $attribute->update(['is_active' => !$attribute->is_active]);

        return $this->success(new AttributeResource($attribute));
    }
}
