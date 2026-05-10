<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Tenant\V1\SiteRequest;
use App\Http\Requests\Api\Tenant\V1\SiteThemeRequest;
use App\Http\Resources\SiteResource;
use App\Http\Resources\SiteThemeResource;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BuilderSiteController extends ApiController
{
    public function show(): JsonResponse
    {
        $site = Site::query()->with('activeTheme')->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        return $this->success(new SiteResource($site));
    }

    public function update(SiteRequest $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $site->update($request->validated());
        $site->load('activeTheme');

        return $this->success(new SiteResource($site));
    }

    public function updateTheme(SiteThemeRequest $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $theme = $site->activeTheme;

        if (! $theme) {
            return $this->notFound('No se encontró el tema activo.');
        }

        $theme->update($request->validated());

        return $this->success(new SiteThemeResource($theme));
    }

    public function setCustomDomain(Request $request): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $data = $request->validate([
            'custom_domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*$/i'],
        ]);

        $token = Str::random(32);

        $site->update([
            'custom_domain' => $data['custom_domain'],
            'domain_status' => 'pending',
            'domain_verification_token' => $token,
            'domain_verified_at' => null,
        ]);

        return $this->success([
            'custom_domain' => $site->custom_domain,
            'domain_status' => $site->domain_status,
            'verification_token' => $token,
            'verification_instructions' => "Add a TXT record to your DNS: _verification.{$data['custom_domain']} with value: {$token}",
        ]);
    }

    public function verifyDomain(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        if (! $site->custom_domain) {
            return $this->error('No hay dominio personalizado configurado.', 422);
        }

        $site->update(['domain_status' => 'verifying']);

        // Check DNS TXT record
        $records = @dns_get_record("_verification.{$site->custom_domain}", DNS_TXT);
        $verified = false;

        if ($records) {
            foreach ($records as $record) {
                if (isset($record['txt']) && $record['txt'] === $site->domain_verification_token) {
                    $verified = true;
                    break;
                }
            }
        }

        if ($verified) {
            $site->update([
                'domain_status' => 'active',
                'domain_verified_at' => now(),
            ]);

            return $this->success([
                'verified' => true,
                'domain_status' => 'active',
            ]);
        }

        $site->update(['domain_status' => 'failed']);

        return $this->success([
            'verified' => false,
            'domain_status' => 'failed',
            'message' => 'No se encontró el registro TXT de verificación.',
        ]);
    }

    public function removeDomain(): JsonResponse
    {
        $site = Site::query()->first();

        if (! $site) {
            return $this->notFound('No se encontró el sitio.');
        }

        $site->update([
            'custom_domain' => null,
            'domain_status' => null,
            'domain_verified_at' => null,
            'domain_verification_token' => null,
        ]);

        return $this->success(new SiteResource($site));
    }
}
