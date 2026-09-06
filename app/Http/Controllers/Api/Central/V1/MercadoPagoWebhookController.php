<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Controller;
use App\Services\MercadoPago\HandleMercadoPagoWebhook;
use App\Services\MercadoPago\MercadoPagoWebhookSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MercadoPagoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        MercadoPagoWebhookSignature $signature,
        HandleMercadoPagoWebhook $handler,
    ): JsonResponse {
        $resourceId = $this->stringValue(
            $request->query('data.id')
            ?: $request->query('data_id')
            ?: $request->input('data.id')
            ?: ''
        );
        $requestId = $this->stringValue($request->header('x-request-id', ''));
        $signatureHeader = $this->stringValue($request->header('x-signature'));

        if (! $signature->isValid(
            $signatureHeader,
            $requestId,
            $resourceId,
        )) {
            return response()->json(['message' => 'Invalid webhook signature.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $payloadId = $this->stringValue($payload['id'] ?? null);
        $eventId = $payloadId !== ''
            ? $payloadId
            : ($requestId !== '' ? $requestId : hash('sha256', serialize($payload)));

        $handler->handle($payload, $resourceId, $eventId);

        return response()->json(['received' => true]);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
