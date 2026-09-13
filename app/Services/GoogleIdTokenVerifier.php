<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Verifica ID tokens de Google Identity Services (Sign in with Google).
 *
 * No confiamos en nada que mande el cliente: el token trae la firma de
 * Google, la validamos contra sus llaves públicas (JWKS, cacheadas) y
 * chequeamos audience/issuer/expiración antes de creer el email.
 */
final class GoogleIdTokenVerifier
{
    private const JWKS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const JWKS_CACHE_KEY = 'google_oauth_jwks';

    private const VALID_ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{sub: string, email: string, name: ?string, picture: ?string}
     */
    public function verify(string $idToken): array
    {
        $clientId = config('services.google.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new RuntimeException('GOOGLE_CLIENT_ID no está configurado.');
        }

        $keys = JWK::parseKeySet($this->jwks());

        /** @var \stdClass $payload */
        $payload = JWT::decode($idToken, $keys);

        if ($payload->aud !== $clientId) {
            throw new RuntimeException('El token de Google no fue emitido para esta aplicación.');
        }

        if (! in_array($payload->iss, self::VALID_ISSUERS, true)) {
            throw new RuntimeException('Issuer de token inválido.');
        }

        if (($payload->email_verified ?? false) !== true || empty($payload->email)) {
            throw new RuntimeException('El email de Google no está verificado.');
        }

        return [
            'sub' => (string) $payload->sub,
            'email' => (string) $payload->email,
            'name' => isset($payload->name) ? (string) $payload->name : null,
            'picture' => isset($payload->picture) ? (string) $payload->picture : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(): array
    {
        return Cache::remember(self::JWKS_CACHE_KEY, now()->addHours(6), function (): array {
            $response = Http::timeout(5)->get(self::JWKS_URL);
            $response->throw();

            return $response->json();
        });
    }
}
