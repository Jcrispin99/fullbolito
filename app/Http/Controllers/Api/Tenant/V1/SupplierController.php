<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SupplierController extends ApiController
{
    /**
     * List suppliers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->companyFiltered()->suppliers();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $status = $request->query('status', 'active');
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $perPage = $request->query('per_page', 15);
        if ($perPage === 'total') {
            $suppliers = $query->get();
            return $this->success(SupplierResource::collection($suppliers));
        }

        $suppliers = $query->paginate((int) $perPage);
        return $this->success(
            SupplierResource::collection($suppliers)->response()->getData(true)
        );
    }

    /**
     * Show a single supplier.
     */
    public function show(Partner $supplier): JsonResponse
    {
        return $this->success(new SupplierResource($supplier->load(['company'])));
    }

    /**
     * Store a new supplier.
     */
    public function store(SupplierRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'is_supplier' => true,
            'is_customer' => false,
            'company_id' => $this->resolveCompanyId($request),
        ]);

        $supplier = DB::transaction(function () use ($data) {
            return Partner::create($data);
        });
        return $this->created(new SupplierResource($supplier->load(['company'])));
    }

    /**
     * Update an existing supplier.
     */
    public function update(SupplierRequest $request, Partner $supplier): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'is_supplier' => true,
            'is_customer' => false,
        ]);

        if (empty($supplier->company_id)) {
            $data['company_id'] = $this->resolveCompanyId($request);
        }

        $supplier->update($data);
        return $this->success(new SupplierResource($supplier->load(['company'])));
    }

    /**
     * Resolve the company_id for the current request:
     * first selected company in X-Company-Ids header, then user's company, then 1.
     */
    private function resolveCompanyId(Request $request): int
    {
        $ids = $request->attributes->get('_company_ids', []);
        if (! empty($ids)) {
            return (int) $ids[0];
        }
        return (int) ($request->user()?->company_id ?? 1);
    }

    /**
     * Delete a supplier.
     */
    public function destroy(Partner $supplier): JsonResponse
    {
        $supplier->delete();
        return $this->noContent();
    }

    /**
     * Toggle the status of a supplier.
     */
    public function toggleStatus(Partner $supplier): JsonResponse
    {
        $supplier->status = $supplier->status === 'active' ? 'inactive' : 'active';
        $supplier->save();
        return $this->success(new SupplierResource($supplier));
    }

    /**
     * Form options for creating/updating a supplier.
     */
    public function formOptions(): JsonResponse
    {
        // Example: return list of companies for dropdown.
        $companies = \App\Models\Company::all(['id', 'business_name']);
        return $this->success([
            'companies' => $companies,
        ]);
    }
}
