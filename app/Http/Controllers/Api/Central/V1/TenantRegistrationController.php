<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\Central\V1\RegisterTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

final class TenantRegistrationController extends ApiController
{
    public function register(RegisterTenantRequest $request, StripeCheckoutService $checkout): JsonResponse
    {
        $fullName = trim($request->first_name . ' ' . $request->last_name);

        $centralUser = User::query()->create([
            'name' => $fullName,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $centralToken = $centralUser->createToken('central-auth-token')->plainTextToken;

        $centralDomain = (string) (config('tenancy.central_domains')[0] ?? '');
        $baseId = Str::slug($request->business_name);
        $baseId = $baseId !== '' ? $baseId : Str::lower(Str::random(8));
        $baseId = mb_substr($baseId, 0, 50);

        $tenantId = $baseId;
        $suffix = 1;
        while (Tenant::query()->whereKey($tenantId)->exists()) {
            $suffix++;
            $tenantId = mb_substr($baseId, 0, 45) . '-' . $suffix;
        }

        $domain = $tenantId . '.' . $centralDomain;

        $tenant = Tenant::query()->create([
            'id' => $tenantId,
            'user_id' => $centralUser->id,
            'business_name' => $request->business_name,
            'owner_email' => $request->email,
            'owner_phone' => $request->phone,
        ]);

        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        $chosenPlan = $request->plan_slug
            ? Plan::query()->where('slug', $request->plan_slug)->first()
            : Plan::query()->where('slug', 'free-trial')->first();

        $isPaidPlan = $chosenPlan && $chosenPlan->stripe_price_id !== null && (float) $chosenPlan->price > 0;

        // Trial / free plans: persist the local subscription immediately.
        // Paid plans: defer subscription creation to the Stripe webhook
        // (customer.subscription.created), triggered after Checkout.
        if ($chosenPlan && ! $isPaidPlan) {
            $tenant->subscriptions()->create([
                'plan_id' => $chosenPlan->id,
                'status' => 'trial',
                'starts_at' => now(),
                'ends_at' => now()->addDays($chosenPlan->duration_days),
                'trial_ends_at' => now()->addDays($chosenPlan->duration_days),
            ]);
        }

        $tenantUser = null;
        $tenantToken = null;

        try {
            tenancy()->initialize($tenant);

            $tenantUser = User::query()->create([
                'name' => $fullName,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $tenantToken = $tenantUser->createToken('tenant-auth-token')->plainTextToken;

            // Sembrar datos base del tenant (el usuario ya existe, se omite UserSeeder)
            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\Tenant\\TenantBaseSeeder',
                '--force' => true,
            ]);
        } finally {
            tenancy()->end();
        }

        if ($tenantUser) {
            $tenantUser->setConnection($centralUser->getConnectionName());
        }

        $tenant->load(['domains', 'subscription.plan']);

        activity()
            ->useLog('tenants')
            ->event('created')
            ->causedBy($centralUser)
            ->withProperties([
                'tenant_id' => $tenant->id,
                'old' => [],
                'attributes' => [
                    'user_id' => $tenant->user_id,
                    'business_name' => $tenant->getAttribute('business_name'),
                    'owner_email' => $tenant->getAttribute('owner_email'),
                    'owner_phone' => $tenant->getAttribute('owner_phone'),
                ],
            ])
            ->log('created');

        $checkoutUrl = null;
        if ($isPaidPlan) {
            $checkoutUrl = $checkout->createSubscriptionCheckout(
                tenant: $tenant,
                plan: $chosenPlan,
                successUrl: URL::to('/billing/success?tenant=' . $tenant->id),
                cancelUrl: URL::to('/billing/cancel?tenant=' . $tenant->id),
            );
        }

        return $this->created([
            'central_user' => new UserResource($centralUser),
            'central_token' => $centralToken,
            'tenant' => new TenantResource($tenant),
            'tenant_user' => new UserResource($tenantUser),
            'tenant_token' => $tenantToken,
            'checkout_url' => $checkoutUrl,
        ], 'Tenant registered successfully');
    }
}
