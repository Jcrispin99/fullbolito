<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\BillingCheckout;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPlanChange;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    /**
     * Applies the commercial plan-change policy:
     * - no provider subscription yet: regular full-price checkout;
     * - higher billing rank: prorated one-time payment and immediate access;
     * - lower/equal rank (cycle change): scheduled for the paid-period end.
     *
     * @return array<string, mixed>
     */
    public function requestPlanChange(Tenant $tenant, Plan $targetPlan, string $successUrl): array
    {
        $subscription = $this->currentProviderSubscription($tenant);

        if (! $subscription || ! $subscription->plan) {
            return [
                'change_type' => 'new_subscription',
                'checkout_url' => $this->createSubscriptionCheckout($tenant, $targetPlan, $successUrl),
                'pending' => true,
                'scheduled' => false,
                'effective_at' => null,
                'proration_amount' => null,
            ];
        }

        if ($subscription->cancel_at_period_end) {
            throw new RuntimeException('La suscripción ya está cancelada y conservará acceso hasta el final del periodo pagado.');
        }

        if ((int) $subscription->plan_id === (int) $targetPlan->id) {
            throw new RuntimeException("El tenant ya usa el plan [{$targetPlan->slug}].");
        }

        $pendingPayment = SubscriptionPlanChange::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['creating', 'pending_payment', 'paid', 'applying', 'ready'])
            ->exists();

        if ($pendingPayment) {
            throw new RuntimeException('Ya existe un cambio de plan en proceso. Complétalo antes de solicitar otro.');
        }

        SubscriptionPlanChange::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled', 'error' => 'Reemplazado por una nueva solicitud.']);

        $anchor = $this->billingCycleAnchor($subscription);
        $currentAmount = $this->amountFor($tenant, $subscription->plan);
        $targetAmount = $this->amountFor($tenant, $targetPlan);
        $currency = mb_strtoupper($this->stringConfig('mercadopago.currency', 'PEN'));
        $isUpgrade = (int) $targetPlan->billing_rank > (int) $subscription->plan->billing_rank;
        $kind = $isUpgrade
            ? 'upgrade'
            : ((int) $targetPlan->billing_rank < (int) $subscription->plan->billing_rank ? 'downgrade' : 'cycle_change');
        $prorationAmount = $isUpgrade
            ? $this->prorationAmount($subscription->plan, $targetPlan, $anchor, $currentAmount, $targetAmount)
            : 0.0;
        $reference = (string) Str::uuid();

        $change = SubscriptionPlanChange::query()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'from_plan_id' => $subscription->plan_id,
            'to_plan_id' => $targetPlan->id,
            'kind' => $kind,
            'status' => $isUpgrade && $prorationAmount > 0 ? 'creating' : ($isUpgrade ? 'paid' : 'scheduled'),
            'external_reference' => $reference,
            'provider' => 'mercadopago',
            'current_recurring_amount' => $currentAmount,
            'target_recurring_amount' => $targetAmount,
            'proration_amount' => $prorationAmount,
            'currency' => $currency,
            'effective_at' => $isUpgrade ? now() : $anchor,
            'billing_cycle_anchor' => $anchor,
            'paid_at' => $isUpgrade && $prorationAmount <= 0 ? now() : null,
        ]);

        if (! $isUpgrade) {
            return $this->planChangeResult($change);
        }

        if ($prorationAmount <= 0) {
            $this->applyPaidUpgrade($change);
            $change->refresh();

            return $this->planChangeResult($change);
        }

        try {
            $email = $this->billingEmail($tenant);
            $failureUrl = str_replace('/billing/success/', '/billing/cancel/', $successUrl);
            $preference = [
                'items' => [[
                    'id' => 'plan-change-'.$change->id,
                    'title' => Str::limit("Diferencia de plan: {$subscription->plan->name} a {$targetPlan->name}", 120, ''),
                    'description' => 'Prorrateo hasta el cierre del periodo de facturación actual.',
                    'quantity' => 1,
                    'currency_id' => $currency,
                    'unit_price' => $prorationAmount,
                ]],
                'payer' => ['email' => $email],
                'external_reference' => $reference,
                'metadata' => [
                    'subscription_plan_change_id' => $change->id,
                    'tenant_id' => (string) $tenant->id,
                    'subscription_id' => $subscription->id,
                ],
                'back_urls' => [
                    'success' => $successUrl,
                    'pending' => $successUrl,
                    'failure' => $failureUrl,
                ],
                'auto_return' => 'approved',
                'expires' => true,
                'expiration_date_to' => now()->addHours($this->intConfig('mercadopago.checkout_ttl_hours', 24))->toIso8601String(),
            ];
            $appUrl = mb_rtrim($this->stringConfig('app.url', ''), '/');

            // Mercado Pago rejects localhost and non-HTTPS notification URLs.
            // The dashboard URL remains the fallback in local development.
            if (str_starts_with($appUrl, 'https://')) {
                $preference['notification_url'] = $appUrl.'/api/v1/webhooks/mercadopago';
            }

            $remote = $this->client->createPreference($preference, $reference);

            $url = $this->stringValue($remote['init_point'] ?? null);
            $preferenceId = $this->stringValue($remote['id'] ?? null);

            if ($url === '' || $preferenceId === '') {
                throw new RuntimeException('Mercado Pago no devolvió una preferencia de pago válida.');
            }

            $change->update([
                'status' => 'pending_payment',
                'provider_preference_id' => $preferenceId,
                'checkout_url' => $url,
                'provider_data' => ['preference' => $remote],
            ]);

            $change->refresh();

            return $this->planChangeResult($change);
        } catch (Throwable $e) {
            $change->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 4000)]);

            throw $e;
        }
    }

    /** @param array<string, mixed> $payment */
    public function recordPlanChangePayment(SubscriptionPlanChange $change, array $payment): void
    {
        $providerStatus = $this->stringValue($payment['status'] ?? null) ?: 'pending';
        $paymentId = $this->stringValue($payment['id'] ?? null);
        $providerData = (array) ($change->provider_data ?? []);
        $providerData['payment'] = $payment;

        if ($change->status === 'applied') {
            $change->update([
                'provider_payment_id' => $paymentId ?: $change->provider_payment_id,
                'provider_data' => $providerData,
                'error' => $providerStatus === 'approved' ? null : "El pago aplicado cambió a estado {$providerStatus}; requiere revisión.",
            ]);

            return;
        }

        if (in_array($change->status, ['cancelled', 'expired'], true)) {
            $change->update([
                'provider_payment_id' => $paymentId ?: $change->provider_payment_id,
                'provider_data' => $providerData,
                'error' => $providerStatus === 'approved'
                    ? 'Se recibió un pago después de cancelar el cambio; requiere revisión y posible devolución.'
                    : $change->error,
            ]);

            return;
        }

        if ($providerStatus !== 'approved') {
            $change->update([
                // A Checkout Pro preference can receive another payment
                // attempt after one card is rejected, so keep it retryable.
                'status' => 'pending_payment',
                'provider_payment_id' => $paymentId ?: $change->provider_payment_id,
                'provider_data' => $providerData,
                'error' => $providerStatus === 'pending' ? null : "Pago de prorrateo: {$providerStatus}.",
            ]);

            return;
        }

        $change->update([
            'status' => 'paid',
            'provider_payment_id' => $paymentId,
            'paid_at' => now(),
            'provider_data' => $providerData,
            'error' => null,
        ]);

        $change->refresh();
        $this->applyPaidUpgrade($change);
    }

    public function applyPaidUpgrade(SubscriptionPlanChange $change): void
    {
        if ($change->status === 'applied') {
            return;
        }

        if ($change->kind !== 'upgrade' || ! in_array($change->status, ['paid', 'applying'], true)) {
            throw new RuntimeException('El cambio no es un upgrade pagado aplicable.');
        }

        $this->updateProviderPlan($change);
        $this->activateLocalPlan($change);
    }

    /** Updates the recurring preapproval shortly before a scheduled change. */
    public function prepareScheduledChange(SubscriptionPlanChange $change): void
    {
        if ($change->status === 'ready') {
            return;
        }

        if ($change->status !== 'scheduled') {
            throw new RuntimeException('El cambio no está programado.');
        }

        $this->updateProviderPlan($change);
        $change->refresh()->update(['status' => 'ready', 'error' => null]);
    }

    public function activateScheduledChange(SubscriptionPlanChange $change): void
    {
        if ($change->status === 'applied') {
            return;
        }

        if ($change->status !== 'ready' || $change->effective_at->isFuture()) {
            throw new RuntimeException('El cambio programado todavía no está listo para activarse.');
        }

        $this->activateLocalPlan($change);
    }

    public function hasActiveSubscription(Tenant $tenant): bool
    {
        return $tenant->subscription()
            ->where('provider', 'mercadopago')
            ->whereNotNull('provider_id')
            ->whereIn('status', ['active', 'past_due', 'paused'])
            ->where('cancel_at_period_end', false)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function cancelAtPeriodEnd(Tenant $tenant, ?string $requestedBy = null, ?string $reason = null): array
    {
        $subscription = $this->providerSubscription($tenant);
        $accessUntil = $subscription->ends_at ?? $subscription->next_billing_at;

        if (! $accessUntil || ! $accessUntil->isFuture()) {
            throw new RuntimeException('La suscripción no tiene un periodo pagado vigente para programar la cancelación.');
        }

        if ($subscription->cancel_at_period_end) {
            return $this->cancellationResult($subscription);
        }

        $hasPaidChangeInProgress = SubscriptionPlanChange::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', ['paid', 'applying'])
            ->exists();

        if ($hasPaidChangeInProgress) {
            throw new RuntimeException(
                'Hay un cambio de plan pagado en proceso. Espera a que termine de aplicarse antes de cancelar la renovación.'
            );
        }

        $remote = $this->client->updateSubscription((string) $subscription->provider_id, [
            'status' => 'cancelled',
        ]);
        $providerStatus = $this->stringValue($remote['status'] ?? null) ?: 'cancelled';

        if (! in_array($providerStatus, ['cancelled', 'canceled'], true)) {
            throw new RuntimeException("Mercado Pago no confirmó la cancelación de la recurrencia ({$providerStatus}).");
        }

        DB::connection($this->centralConnection())->transaction(function () use (
            $subscription,
            $accessUntil,
            $remote,
            $providerStatus,
            $requestedBy,
            $reason,
        ): void {
            /** @var Subscription $locked */
            $locked = Subscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $locked->update([
                'status' => 'active',
                'provider_status' => $providerStatus,
                'ends_at' => $accessUntil,
                'cancel_at_period_end' => true,
                'cancellation_requested_at' => now(),
                'cancellation_requested_by' => $requestedBy,
                'cancellation_reason' => $reason,
                'provider_data' => $remote,
            ]);

            SubscriptionPlanChange::query()
                ->where('tenant_id', $locked->tenant_id)
                ->whereIn('status', ['creating', 'pending_payment', 'scheduled', 'ready'])
                ->update([
                    'status' => 'cancelled',
                    'error' => 'Cancelado porque el cliente solicitó finalizar su suscripción.',
                ]);
        });

        /** @var Subscription $freshSubscription */
        $freshSubscription = $subscription->fresh() ?? $subscription;

        return $this->cancellationResult($freshSubscription);
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

        if ($subscription->cancel_at_period_end) {
            throw new RuntimeException('La renovación ya fue cancelada. La suscripción conservará acceso hasta el final del periodo pagado.');
        }

        return $this->client->updateSubscription((string) $subscription->provider_id, [
            'status' => $status,
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

    /** @return array<string, mixed> */
    private function cancellationResult(Subscription $subscription): array
    {
        return [
            'status' => $subscription->status,
            'provider_status' => $subscription->provider_status,
            'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
            'cancellation_requested_at' => $subscription->cancellation_requested_at?->toIso8601String(),
            'access_until' => $subscription->ends_at?->toIso8601String(),
        ];
    }

    private function currentProviderSubscription(Tenant $tenant): ?Subscription
    {
        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscriptions()
            ->with('plan')
            ->where('provider', 'mercadopago')
            ->whereNotNull('provider_id')
            ->whereIn('status', ['active', 'past_due', 'paused'])
            ->latest('id')
            ->first();

        return $subscription;
    }

    private function billingCycleAnchor(Subscription $subscription): Carbon
    {
        $anchor = $subscription->next_billing_at ?? $subscription->ends_at;

        if (! $anchor || $anchor->isPast()) {
            throw new RuntimeException('La suscripción no tiene una próxima fecha de cobro válida. Sincronízala antes de cambiar de plan.');
        }

        return Carbon::instance($anchor);
    }

    private function prorationAmount(
        Plan $currentPlan,
        Plan $targetPlan,
        Carbon $anchor,
        float $currentAmount,
        float $targetAmount,
    ): float {
        $remainingSeconds = (float) now()->diffInSeconds($anchor, false);
        $remainingDays = min(
            (float) max(1, (int) $currentPlan->duration_days),
            max(0.0, $remainingSeconds / 86400),
        );
        $currentDaily = $currentAmount / max(1, (int) $currentPlan->duration_days);
        $targetDaily = $targetAmount / max(1, (int) $targetPlan->duration_days);

        return round(max(0.0, ($targetDaily - $currentDaily) * $remainingDays), 2);
    }

    private function updateProviderPlan(SubscriptionPlanChange $change): void
    {
        $change->loadMissing(['subscription', 'toPlan']);
        /** @var Subscription|null $subscription */
        $subscription = $change->subscription;
        /** @var Plan|null $targetPlan */
        $targetPlan = $change->toPlan;

        if (! $subscription || ! $targetPlan || ! $subscription->provider_id) {
            throw new RuntimeException('El cambio no tiene una suscripción de Mercado Pago válida.');
        }

        $change->update([
            'status' => $change->kind === 'upgrade' ? 'applying' : $change->status,
            'last_attempt_at' => now(),
            'attempts' => (int) $change->attempts + 1,
            'error' => null,
        ]);

        try {
            $remote = $this->client->updateSubscription((string) $subscription->provider_id, [
                'reason' => $this->reasonFor($targetPlan),
                'auto_recurring' => [
                    ...$this->frequencyFor($targetPlan),
                    'transaction_amount' => (float) $change->target_recurring_amount,
                    'currency_id' => $change->currency,
                ],
            ]);
            $providerData = (array) ($change->provider_data ?? []);
            $providerData['subscription_update'] = $remote;
            $change->update(['provider_data' => $providerData]);

            $subscription->update([
                'provider_status' => $this->stringValue($remote['status'] ?? null) ?: $subscription->provider_status,
                'next_billing_at' => $this->date($remote['next_payment_date'] ?? null) ?? $subscription->next_billing_at,
                'ends_at' => $this->date($remote['next_payment_date'] ?? null) ?? $subscription->ends_at,
                'provider_data' => $remote,
            ]);
        } catch (Throwable $e) {
            $change->update(['error' => mb_substr($e->getMessage(), 0, 4000)]);

            throw $e;
        }
    }

    private function activateLocalPlan(SubscriptionPlanChange $change): void
    {
        DB::connection($this->centralConnection())->transaction(function () use ($change): void {
            /** @var SubscriptionPlanChange $locked */
            $locked = SubscriptionPlanChange::query()->lockForUpdate()->findOrFail($change->id);

            if ($locked->status === 'applied') {
                return;
            }

            /** @var Subscription $subscription */
            $subscription = Subscription::query()->lockForUpdate()->findOrFail($locked->subscription_id);
            $subscription->update(['plan_id' => $locked->to_plan_id]);
            $locked->update([
                'status' => 'applied',
                'effective_at' => $locked->kind === 'upgrade' ? now() : $locked->effective_at,
                'applied_at' => now(),
                'error' => null,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function planChangeResult(SubscriptionPlanChange $change): array
    {
        return [
            'change_id' => $change->id,
            'change_type' => $change->kind,
            'status' => $change->status,
            'checkout_url' => $change->checkout_url,
            'pending' => ! in_array($change->status, ['applied', 'cancelled', 'failed'], true),
            'scheduled' => in_array($change->status, ['scheduled', 'ready'], true),
            'effective_at' => $change->effective_at->toIso8601String(),
            'proration_amount' => (float) $change->proration_amount,
            'recurring_amount' => (float) $change->target_recurring_amount,
            'currency' => $change->currency,
        ];
    }

    private function billingEmail(Tenant $tenant): string
    {
        $email = mb_trim($this->stringValue($tenant->getAttribute('owner_email')));

        if ($email === '') {
            $email = mb_trim($this->stringValue($tenant->owner()->value('email')));
        }

        if ($email === '') {
            throw new RuntimeException('El tenant no tiene un correo de facturación válido.');
        }

        return $email;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || mb_trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function centralConnection(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
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
