<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CategoryController extends ApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 15);
        $search = $request->input('search');
        $status = $request->input('status');

        $query = Category::query()->orderBy('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('full_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
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

        if ($perPage === '-1' || $perPage === 'total') {
            $categories = $query->get();

            return $this->success(CategoryResource::collection($categories));
        }

        $categories = $query->paginate((int) $perPage);

        return $this->success(
            CategoryResource::collection($categories)->response()->getData(true)
        );
    }

    public function formOptions(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return $this->success([
            'parent_categories' => CategoryResource::collection($categories),
        ], 'Form options retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return $this->created(new CategoryResource($category));
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): JsonResponse
    {
        return $this->success(new CategoryResource($category));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());

        return $this->success(new CategoryResource($category));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Category::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggleStatus(Category $category): JsonResponse
    {
        $category->update(['is_active' => !$category->is_active]);

        return $this->success(new CategoryResource($category));
    }
}
