<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\PaymentProviderConnection;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

final class TenantMercadoPagoConnectionService
{
    public const PROVIDER = 'mercadopago';

    public function importFromConfig(string $environment): PaymentProviderConnection
    {
        $environment = $this->environment($environment);
        $accessToken = $this->configString('mercadopago.access_token');

        if ($accessToken === '') {
            throw new RuntimeException('MERCADOPAGO_ACCESS_TOKEN no está configurado.');
        }

        $connection = PaymentProviderConnection::query()->firstOrNew([
            'provider' => self::PROVIDER,
            'environment' => $environment,
        ]);

        $connection->fill([
            'access_token' => $accessToken,
            'public_key' => $this->nullableConfigString('mercadopago.public_key'),
            'webhook_secret' => $this->nullableConfigString('mercadopago.webhook_secret'),
            'external_account_id' => null,
            'account_nickname' => null,
            'country_id' => null,
            'is_active' => false,
            'validated_at' => null,
            'last_validation_error' => null,
            'metadata' => [
                'source' => 'environment_import',
                'imported_at' => now()->toIso8601String(),
            ],
        ]);
        $connection->save();

        return $connection->fresh() ?? $connection;
    }

    /** @return array{provider: string, environment: string, account_id: string, nickname: string|null, country_id: string|null, validated_at: string} */
    public function test(PaymentProviderConnection $connection): array
    {
        if ($connection->provider !== self::PROVIDER) {
            throw new RuntimeException('La conexión no pertenece a Mercado Pago.');
        }

        try {
            $response = Http::acceptJson()
                ->withToken($connection->access_token)
                ->timeout($this->configInt('mercadopago.timeout', 15))
                ->get($this->baseUrl().'/users/me');

            if (! $response->successful()) {
                throw new RuntimeException("Mercado Pago respondió HTTP {$response->status()} al validar la cuenta.");
            }

            $accountId = $this->stringValue($response->json('id'));

            if ($accountId === '') {
                throw new RuntimeException('Mercado Pago no devolvió el identificador de la cuenta vendedora.');
            }

            $nickname = $this->nullableString($response->json('nickname'));
            $countryId = $this->nullableString($response->json('country_id'));
            $validatedAt = now();

            $connection->update([
                'external_account_id' => $accountId,
                'account_nickname' => $nickname,
                'country_id' => $countryId,
                'is_active' => true,
                'validated_at' => $validatedAt,
                'last_validation_error' => null,
            ]);

            return [
                'provider' => self::PROVIDER,
                'environment' => $connection->environment,
                'account_id' => $accountId,
                'nickname' => $nickname,
                'country_id' => $countryId,
                'validated_at' => $validatedAt->toIso8601String(),
            ];
        } catch (Throwable $e) {
            $message = $e instanceof ConnectionException
                ? 'No se pudo conectar con Mercado Pago.'
                : $e->getMessage();

            $connection->update([
                'is_active' => false,
                'validated_at' => null,
                'last_validation_error' => mb_substr($message, 0, 2000),
            ]);

            throw new RuntimeException($message, previous: $e);
        }
    }

    public function find(string $environment): PaymentProviderConnection
    {
        /** @var PaymentProviderConnection|null $connection */
        $connection = PaymentProviderConnection::query()
            ->where('provider', self::PROVIDER)
            ->where('environment', $this->environment($environment))
            ->first();

        if (! $connection) {
            throw new RuntimeException('El tenant no tiene credenciales de Mercado Pago importadas para ese ambiente.');
        }

        return $connection;
    }

    private function environment(string $environment): string
    {
        $environment = mb_strtolower(mb_trim($environment));

        if (! in_array($environment, ['test', 'production'], true)) {
            throw new RuntimeException('El ambiente debe ser test o production.');
        }

        return $environment;
    }

    private function baseUrl(): string
    {
        return mb_rtrim($this->configString('mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    private function configString(string $key, string $default = ''): string
    {
        return $this->stringValue(config($key, $default));
    }

    private function nullableConfigString(string $key): ?string
    {
        return $this->nullableString(config($key));
    }

    private function configInt(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->stringValue($value);

        return $value !== '' ? $value : null;
    }
}
