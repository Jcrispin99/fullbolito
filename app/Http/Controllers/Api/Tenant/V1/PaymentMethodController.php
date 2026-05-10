<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\PaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentMethodController extends ApiController
{
    /**
     * Listar Métodos de Pago
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
            'per_page' => 'nullable',
        ]);

        $query = PaymentMethod::query()->orderBy('name', 'asc');

        $search = $request->input('q');
        if (is_string($search) && $search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        $status = $request->input('status');
        if (is_string($status) && $status !== '') {
            $query->where('is_active', $status === 'active');
        }

        $perPage = $request->input('per_page', 50);

        if ($perPage === '-1' || $perPage === 'total') {
            return $this->success(PaymentMethodResource::collection($query->get()));
        }

        $perPageInt = is_numeric($perPage) ? (int) $perPage : 50;

        return $this->success(
            PaymentMethodResource::collection($query->paginate($perPageInt))->response()->getData(true)
        );
    }

    /**
     * Crear Método de Pago
     */
    public function store(PaymentMethodRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paymentMethod = PaymentMethod::create($validated);

        return response()->json([
            'message' => 'Método de pago creado exitosamente.',
            'data' => new PaymentMethodResource($paymentMethod),
        ], 201);
    }

    /**
     * Ver Método de Pago
     */
    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        return new PaymentMethodResource($paymentMethod);
    }

    /**
     * Actualizar Método de Pago
     */
    public function update(PaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validated();

        $paymentMethod->update($validated);

        return response()->json([
            'message' => 'Método de pago actualizado exitosamente.',
            'data' => new PaymentMethodResource($paymentMethod),
        ]);
    }

    /**
     * Eliminar Método de Pago
     */
    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        // Add basic protection against active deletion if linked (e.g., to pos_session_payments)
        // Since pos_session_payments isn't fully scoped yet, returning basic delete for now.
        $paymentMethod->delete();

        return response()->json([
            'message' => 'Método de pago eliminado exitosamente.',
        ]);
    }

    /**
     * Cambiar Estado
     */
    public function toggleStatus(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update([
            'is_active' => ! $paymentMethod->is_active,
        ]);

        return $this->success(new PaymentMethodResource($paymentMethod->fresh()));
    }
}
