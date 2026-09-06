<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\BillingCheckout;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MercadoPagoBillingService
{
    public function __construct(private readonly MercadoPagoClient $client) {}

    public function createSubscriptionCheckout(Tenant $tenant, Plan $plan, string $backUrl): string
    {
        $email = mb_trim($this->stringValue($tenant->getAttribute('owner_email')));

        if ($email === '') {
            $email = mb_trim($this->stringValue($tenant->owner()->value('email')));
        }

        if ($email === '') {
            throw new RuntimeException('El tenant no tiene un correo de facturación válido.');
        }

        $reference = (string) Str::uuid();
        $amount = $this->amountFor($tenant, $plan);
        $currency = mb_strtoupper($this->stringConfig('mercadopago.currency', 'PEN'));

        if ($amount <= 0) {
            throw new RuntimeException('No se puede crear una suscripción de monto cero.');
        }

        $checkout = BillingCheckout::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'replaces_subscription_id' => $tenant->subscription()
                ->where('provider', 'mercadopago')
                ->whereNotNull('provider_id')
                ->value('id'),
            'provider' => 'mercadopago',
            'external_reference' => $reference,
            'status' => 'creating',
            'amount' => $amount,
            'currency' => $currency,
            'expires_at' => now()->addHours($this->intConfig('mercadopago.checkout_ttl_hours', 24)),
        ]);

        try {
            $remote = $this->client->createSubscription([
                'reason' => $this->reasonFor($plan),
                'external_reference' => $reference,
                'payer_email' => $email,
                'auto_recurring' => [
                    ...$this->frequencyFor($plan),
                    'transaction_amount' => $amount,
                    'currency_id' => $currency,
                ],
                'back_url' => $backUrl,
                'status' => 'pending',
            ], $reference);

            $url = $this->stringValue($remote['init_point'] ?? null);
            $providerId = $this->stringValue($remote['id'] ?? null);

            if ($url === '' || $providerId === '') {
                throw new RuntimeException('Mercado Pago no devolvió un checkout válido.');
            }

            $checkout->update([
                'provider_id' => $providerId,
                'status' => $this->stringValue($remote['status'] ?? null) ?: 'pending',
                'checkout_url' => $url,
                'provider_data' => $remote,
            ]);

            return $url;
        } catch (Throwable $e) {
            $checkout->update(['status' => 'failed', 'provider_data' => ['error' => $e->getMessage()]]);

            throw $e;
        }
    }

    public function hasActiveSubscription(Tenant $tenant): bool
    {
        return $tenant->subscription()
            ->where('provider', 'mercadopago')
            ->whereNotNull('provider_id')
            ->whereIn('status', ['active', 'past_due', 'paused'])
            ->exists();
    }

    /**
     * @param  list<string>  $addonKeys
     * @return array<string, mixed>
     */
    public function updateForAddons(Tenant $tenant, array $addonKeys): array
    {
        $subscription = $this->providerSubscription($tenant);
        /** @var Plan|null $plan */
        $plan = $subscription->plan;

        if (! $plan) {
            throw new RuntimeException('La suscripción no tiene un plan asociado.');
        }

        return $this->client->updateSubscription((string) $subscription->provider_id, [
            'auto_recurring' => [
                'transaction_amount' => $this->amountFor($tenant, $plan, $addonKeys),
                'currency_id' => mb_strtoupper($this->stringConfig('mercadopago.currency', 'PEN')),
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function changeStatus(Tenant $tenant, string $status): array
    {
        $subscription = $this->providerSubscription($tenant);
        $providerStatus = $status === 'cancelled' ? 'canceled' : $status;

        return $this->client->updateSubscription((string) $subscription->provider_id, [
            'status' => $providerStatus,
        ]);
    }

    /** @param list<string>|null $addonKeys */
    public function amountFor(Tenant $tenant, Plan $plan, ?array $addonKeys = null): float
    {
        $addonKeys ??= $tenant->getAddonFeatures();
        $includedKeys = $plan->includes_all_modules
            ? Module::query()->active()->pluck('key')->all()
            : $plan->modules()->pluck('key')->all();

        $billableKeys = array_values(array_diff($addonKeys, $includedKeys));
        $addonTotal = $billableKeys === []
            ? 0.0
            : (float) Module::query()->active()->whereIn('key', $billableKeys)->sum('addon_price');

        return round((float) $plan->price + $addonTotal, 2);
    }

    private function providerSubscription(Tenant $tenant): Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscription()
            ->with('plan')
            ->where('provider', 'mercadopago')
            ->whereNotNull('provider_id')
            ->first();

        if (! $subscription) {
            throw new RuntimeException('El tenant no tiene una suscripción de Mercado Pago vinculada.');
        }

        return $subscription;
    }

    /** @return array{frequency: int, frequency_type: string} */
    private function frequencyFor(Plan $plan): array
    {
        if ((int) $plan->duration_days >= 365) {
            return ['frequency' => 12, 'frequency_type' => 'months'];
        }

        if ((int) $plan->duration_days >= 28 && (int) $plan->duration_days <= 31) {
            return ['frequency' => 1, 'frequency_type' => 'months'];
        }

        return ['frequency' => max(1, (int) $plan->duration_days), 'frequency_type' => 'days'];
    }

    private function reasonFor(Plan $plan): string
    {
        return Str::limit('Fullbolito - '.$plan->name, 120, '');
    }

    private function stringConfig(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    private function intConfig(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
