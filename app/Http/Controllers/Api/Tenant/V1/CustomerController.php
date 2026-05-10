<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\CustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CustomerController extends ApiController
{
    /**
     * List customers with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->customers();

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
            $customers = $query->get();
            return $this->success(CustomerResource::collection($customers));
        }

        $customers = $query->paginate((int) $perPage);
        return $this->success(
            CustomerResource::collection($customers)->response()->getData(true)
        );
    }

    /**
     * Show a single customer.
     */
    public function show(Partner $customer): JsonResponse
    {
        return $this->success(new CustomerResource($customer->load(['company'])));
    }

    /**
     * Store a new customer.
     */
    public function store(CustomerRequest $request): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'is_customer' => true,
            'is_supplier' => false,
        ]);

        $customer = DB::transaction(function () use ($data) {
            return Partner::create($data);
        });
        return $this->created(new CustomerResource($customer->load(['company'])));
    }

    /**
     * Update an existing customer.
     */
    public function update(CustomerRequest $request, Partner $customer): JsonResponse
    {
        $data = array_merge($request->validated(), [
            'is_customer' => true,
            'is_supplier' => false,
        ]);

        $customer->update($data);
        return $this->success(new CustomerResource($customer->load(['company'])));
    }

    /**
     * Delete a customer.
     */
    public function destroy(Partner $customer): JsonResponse
    {
        $customer->delete();
        return $this->noContent();
    }

    /**
     * Toggle the status of a customer.
     */
    public function toggleStatus(Partner $customer): JsonResponse
    {
        $customer->status = $customer->status === 'active' ? 'inactive' : 'active';
        $customer->save();
        return $this->success(new CustomerResource($customer));
    }

    /**
     * Form options for creating/updating a customer.
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
