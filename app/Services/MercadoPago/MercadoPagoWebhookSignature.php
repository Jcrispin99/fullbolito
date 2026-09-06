<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

final class MercadoPagoWebhookSignature
{
    public function isValid(
        ?string $signatureHeader,
        ?string $requestId,
        ?string $dataId,
        ?string $secret = null,
    ): bool {
        if ($secret === null) {
            $configuredSecret = config('mercadopago.webhook_secret');
            $secret = is_string($configuredSecret) ? $configuredSecret : '';
        }

        if ($secret === '' || ! $signatureHeader || ! $requestId || ! $dataId) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', mb_trim($part), 2), 2, null);
            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['ts'] ?? null;
        $receivedHash = $parts['v1'] ?? null;

        if (! $timestamp || ! $receivedHash || ! ctype_digit($timestamp)) {
            return false;
        }

        $manifest = sprintf(
            'id:%s;request-id:%s;ts:%s;',
            mb_strtolower($dataId),
            $requestId,
            $timestamp,
        );

        return hash_equals(hash_hmac('sha256', $manifest, $secret), $receivedHash);
    }
}
