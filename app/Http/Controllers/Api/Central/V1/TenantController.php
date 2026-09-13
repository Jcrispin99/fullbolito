<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Central\V1\TenantRequest;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TenantController extends ApiController
{
    public function index(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can list all tenants.');
        }

        $tenants = Tenant::query()
            ->with('domains')
            ->orderBy('id')
            ->paginate();

        return $this->success(TenantResource::collection($tenants));
    }

    public function myTenants(): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $tenants = Tenant::query()
            ->where('user_id', $user->id)
            ->orWhere('data->user_id', $user->id)
            ->with('domains')
            ->orderBy('created_at', 'desc')
            ->paginate();

        return $this->success(TenantResource::collection($tenants));
    }

    public function show(Tenant $tenant): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin() && (int) $tenant->user_id !== (int) $user->id) {
            return $this->forbidden('You are not allowed to view this tenant.');
        }

        $tenant->load([
            'domains',
            'subscription.plan',
            'subscription.payments',
            'subscriptions.plan',
            'subscriptions.payments',
        ]);

        return $this->success(new TenantResource($tenant));
    }

    public function store(TenantRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can create tenants.');
        }

        $domains = $request->domains ?? [$request->domain];

        $payload = [
            'id' => $request->id,
            'user_id' => $request->user_id ?? Auth::id(),
        ];

        if (is_array($request->data)) {
            foreach ($request->data as $key => $value) {
                if (! is_string($key) || $key === '' || $key === 'id' || $key === 'user_id' || $key === 'data') {
                    continue;
                }
                if (str_starts_with($key, Tenant::internalPrefix())) {
                    continue;
                }
                $payload[$key] = $value;
            }
        }

        $tenant = Tenant::query()->create($payload);

        foreach ($domains as $domain) {
            $tenant->domains()->create([
                'domain' => $domain,
            ]);
        }

        if ($tenant->user_id) {
            $this->provisionTenantOwner($tenant);
        }

        $tenant->load('domains');

        return $this->created(new TenantResource($tenant));
    }

    public function update(TenantRequest $request, Tenant $tenant): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can update tenants.');
        }

        $old = [
            'user_id' => $tenant->user_id,
            'business_name' => $tenant->getAttribute('business_name'),
            'owner_email' => $tenant->getAttribute('owner_email'),
            'owner_phone' => $tenant->getAttribute('owner_phone'),
        ];

        if ($request->has('user_id')) {
            $tenant->user_id = $request->user_id;
        }

        if ($request->has('data')) {
            $reserved = ['id', 'user_id', 'created_at', 'updated_at', 'data'];
            $existingKeys = array_keys($tenant->getAttributes());
            foreach ($existingKeys as $key) {
                if (in_array($key, $reserved, true)) {
                    continue;
                }
                if (str_starts_with($key, Tenant::internalPrefix())) {
                    continue;
                }
                if (! is_array($request->data) || ! array_key_exists($key, $request->data)) {
                    unset($tenant->$key);
                }
            }

            if (is_array($request->data)) {
                foreach ($request->data as $key => $value) {
                    if (! is_string($key) || $key === '' || in_array($key, $reserved, true)) {
                        continue;
                    }
                    if (str_starts_with($key, Tenant::internalPrefix())) {
                        continue;
                    }
                    $tenant->setAttribute($key, $value);
                }
            }
        }

        $tenant->save();

        $attributes = [
            'user_id' => $tenant->user_id,
            'business_name' => $tenant->getAttribute('business_name'),
            'owner_email' => $tenant->getAttribute('owner_email'),
            'owner_phone' => $tenant->getAttribute('owner_phone'),
        ];

        activity()
            ->useLog('tenants')
            ->event('updated')
            ->causedBy($user)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'old' => $old,
                'attributes' => $attributes,
            ])
            ->log('updated');

        if ($request->has('domains') || $request->has('domain')) {
            $domains = $request->domains ?? ($request->domain ? [$request->domain] : []);

            $domains = array_values(array_unique(array_filter($domains, fn($d) => is_string($d) && $d !== '')));

            $tenant->domains()
                ->whereNotIn('domain', $domains)
                ->delete();

            $existing = $tenant->domains()->pluck('domain')->all();
            foreach ($domains as $domain) {
                if (! in_array($domain, $existing, true)) {
                    $tenant->domains()->create(['domain' => $domain]);
                }
            }
        }

        $tenant->load('domains');

        return $this->success(new TenantResource($tenant));
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can delete tenants.');
        }

        $this->deleteTenantWithLog($tenant, $user);

        return $this->noContent();
    }

    public function batchDestroy(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (! $user->isSuperAdmin()) {
            return $this->forbidden('Only superadmins can delete tenants.');
        }

        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['string', 'exists:tenants,id'],
        ]);

        $tenants = Tenant::query()->whereIn('id', $validated['ids'])->get();

        foreach ($tenants as $tenant) {
            $this->deleteTenantWithLog($tenant, $user);
        }

        return $this->noContent();
    }

    private function deleteTenantWithLog(Tenant $tenant, User $user): void
    {
        $old = [
            'user_id' => $tenant->user_id,
            'business_name' => $tenant->getAttribute('business_name'),
            'owner_email' => $tenant->getAttribute('owner_email'),
            'owner_phone' => $tenant->getAttribute('owner_phone'),
        ];

        activity()
            ->useLog('tenants')
            ->event('deleted')
            ->causedBy($user)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'old' => $old,
                'attributes' => [],
            ])
            ->log('deleted');

        $tenant->delete();
    }

    /**
     * Crea, dentro de la base de datos del tenant recién creado, el usuario
     * correspondiente al dueño central (`tenant.user_id`) y le asigna el rol
     * admin. Sin esto, el owner nunca podría entrar a su propio tenant: cada
     * tenant tiene su propia tabla `users`, separada de la central.
     *
     * No se conoce la contraseña del owner en este flujo (lo crea un
     * superadmin), así que se genera una aleatoria y el owner debe usar
     * "forgot password" (disponible también en el dominio del tenant) para
     * fijar la suya antes de iniciar sesión.
     */
    private function provisionTenantOwner(Tenant $tenant): void
    {
        $owner = User::query()->find($tenant->user_id);

        if (! $owner) {
            return;
        }

        try {
            tenancy()->initialize($tenant);

            $tenantUser = User::query()->create([
                'name' => $owner->name,
                'email' => $owner->email,
                'password' => Hash::make(Str::random(40)),
            ]);

            $tenantUser->assignRole('admin');
        } finally {
            tenancy()->end();
        }
    }
}
