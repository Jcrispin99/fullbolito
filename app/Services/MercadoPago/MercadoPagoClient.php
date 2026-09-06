<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use RuntimeException;

final class MercadoPagoClient
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createSubscription(array $payload, string $idempotencyKey): array
    {
        return $this->json($this->request()
            ->withHeader('X-Idempotency-Key', $idempotencyKey)
            ->post('/preapproval', $payload)
            ->throw());
    }

    /** @return array<string, mixed> */
    public function getSubscription(string $id): array
    {
        return $this->json($this->request()->get('/preapproval/'.$id)->throw());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateSubscription(string $id, array $payload): array
    {
        return $this->json($this->request()->put('/preapproval/'.$id, $payload)->throw());
    }

    /** @return array<string, mixed> */
    public function getAuthorizedPayment(string $id): array
    {
        return $this->json($this->request()->get('/authorized_payments/'.$id)->throw());
    }

    /** @return array<string, mixed> */
    public function getPayment(string $id): array
    {
        return $this->json($this->request()->get('/v1/payments/'.$id)->throw());
    }

    private function request(): PendingRequest
    {
        $accessToken = $this->stringConfig('mercadopago.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('MERCADOPAGO_ACCESS_TOKEN is not configured.');
        }

        return \Illuminate\Support\Facades\Http::baseUrl(
            mb_rtrim($this->stringConfig('mercadopago.base_url', 'https://api.mercadopago.com'), '/'),
        )
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->connectTimeout(5)
            ->timeout($this->intConfig('mercadopago.timeout', 15));
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Mercado Pago devolvió una respuesta JSON inválida.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    private function stringConfig(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }
}
