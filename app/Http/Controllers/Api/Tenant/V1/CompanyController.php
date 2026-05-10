<?php

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function formOptions(): JsonResponse
    {
        $excludeId = request()->input('exclude_id');

        $query = Company::query()->orderBy('business_name');

        if (is_numeric($excludeId)) {
            $query->where('id', '!=', (int) $excludeId);
        }

        $companies = $query->get();

        return $this->success([
            'parent_companies' => CompanyResource::collection($companies),
        ], 'Form options retrieved successfully');
    }

    public function index(): JsonResponse
    {
        $perPage = request()->input('per_page', 15);
        $search = request()->input('search');
        $status = request()->input('status');

        $query = Company::query()->orderBy('id');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%")
                    ->orWhere('ruc', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
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
            $companies = $query->get();
            return $this->success(CompanyResource::collection($companies));
        }

        $companies = $query->paginate((int) $perPage);

        return $this->success(
            CompanyResource::collection($companies)->response()->getData(true)
        );
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        $company = Company::query()->create($request->validated());

        return $this->created(new CompanyResource($company));
    }

    public function show(Company $company): JsonResponse
    {
        return $this->success(new CompanyResource($company));
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse
    {
        $company->fill($request->validated());
        $company->save();

        return $this->success(new CompanyResource($company));
    }

    public function destroy(Company $company): JsonResponse
    {
        $company->delete();

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        Company::whereIn('id', $request->ids)->delete();
        return $this->noContent();
    }

    /**
     * Toggle the active status of the specified resource.
     */
    public function toggleStatus(Company $company): JsonResponse
    {
        $company->update(['is_active' => !$company->is_active]);

        return $this->success(new CompanyResource($company));
    }
}
