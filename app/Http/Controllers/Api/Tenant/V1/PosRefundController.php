<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\PosRefundRequest;
use App\Http\Resources\SaleResource;
use App\Models\PosSession;
use App\Models\Sale;
use App\Services\SaleRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class PosRefundController extends ApiController
{
    private const SALE_LOAD_RELATIONS = [
        'partner',
        'warehouse',
        'company',
        'journal',
        'user',
        'products.productProduct.template.uom',
        'products.tax',
        'products.uom',
        'originalSale.journal',
        'creditNotes.journal',
        'creditNotes.products',
    ];

    public function store(PosRefundRequest $request, SaleRefundService $service): JsonResponse
    {
        $validated = $request->validated();

        $original = Sale::query()->findOrFail($validated['original_sale_id']);
        $session = PosSession::query()->findOrFail($validated['pos_session_id']);

        if (! $session->isOpen()) {
            throw ValidationException::withMessages([
                'pos_session_id' => 'La sesión POS no está abierta.',
            ]);
        }

        if ((int) $original->company_id !== (int) $session->posConfig?->company_id) {
            throw ValidationException::withMessages([
                'original_sale_id' => 'La venta original no pertenece a la compañía de la caja.',
            ]);
        }

        $note = $service->createFromOriginal(
            $original,
            [
                'lines' => $validated['lines'],
                'notes' => $validated['notes'] ?? null,
                'pos_session_id' => $validated['pos_session_id'],
            ],
            $request->user(),
        );

        return $this->created(
            new SaleResource($note->load(self::SALE_LOAD_RELATIONS)),
            'Nota de crédito generada correctamente',
        );
    }
}
