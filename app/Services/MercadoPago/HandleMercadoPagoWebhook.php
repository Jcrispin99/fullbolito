<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\BillingCheckout;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class HandleMercadoPagoWebhook
{
    public function __construct(private readonly MercadoPagoClient $client) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload, string $resourceId, string $eventId): void
    {
        $type = $this->stringValue($payload['type'] ?? null) ?: 'unknown';
        $event = PaymentWebhookEvent::query()->firstOrCreate(
            ['provider' => 'mercadopago', 'event_id' => $eventId],
            [
                'event_type' => $type,
                'action' => $payload['action'] ?? null,
                'resource_id' => $resourceId,
                'payload' => $payload,
            ],
        );

        if ($event->processed_at !== null) {
            return;
        }

        try {
            match ($type) {
                'subscription_preapproval' => $this->syncPreapproval(
                    $this->client->getSubscription($resourceId),
                ),
                'subscription_authorized_payment' => $this->syncAuthorizedPayment(
                    $this->client->getAuthorizedPayment($resourceId),
                ),
                'payment' => $this->syncPayment($this->client->getPayment($resourceId)),
                default => Log::info('Webhook de Mercado Pago ignorado', [
                    'type' => $type,
                    'resource_id' => $resourceId,
                ]),
            };

            $event->update(['processed_at' => now(), 'error' => null]);
        } catch (Throwable $e) {
            $event->update(['error' => mb_substr($e->getMessage(), 0, 4000)]);

            throw $e;
        }
    }

    /** @param array<string, mixed> $remote */
    public function syncPreapproval(array $remote): void
    {
        $providerId = $this->stringValue($remote['id'] ?? null);
        $externalReference = $this->stringValue($remote['external_reference'] ?? null);
        $providerStatus = $this->stringValue($remote['status'] ?? null) ?: 'pending';

        if ($providerId === '') {
            return;
        }

        $checkout = BillingCheckout::query()
            ->where('provider_id', $providerId)
            ->when($externalReference !== '', fn ($query) => $query->orWhere('external_reference', $externalReference))
            ->first();

        $subscription = Subscription::query()->where('provider_id', $providerId)->first();
        $localStatus = $this->mapSubscriptionStatus($providerStatus);

        if (! $subscription && $localStatus !== 'active') {
            if ($checkout) {
                $checkout->update([
                    'status' => $providerStatus,
                    'provider_data' => $remote,
                ]);
            }

            return;
        }

        if (! $subscription && ! $checkout) {
            Log::warning('Suscripción autorizada de Mercado Pago sin checkout local', [
                'provider_id' => $providerId,
                'external_reference' => $externalReference,
            ]);

            return;
        }

        if (! $subscription && $checkout->replaces_subscription_id) {
            $replaced = Subscription::query()->find($checkout->replaces_subscription_id);

            if ($replaced?->provider_id && $replaced->provider_id !== $providerId) {
                $this->client->updateSubscription((string) $replaced->provider_id, [
                    'status' => 'canceled',
                ]);
            }
        }

        DB::connection($this->centralConnection())->transaction(function () use (
            $remote,
            $providerId,
            $providerStatus,
            $externalReference,
            $localStatus,
            $checkout,
            $subscription,
        ): void {
            $startsAt = $this->date($remote['date_created'] ?? null) ?? now();
            $nextBillingAt = $this->date($remote['next_payment_date'] ?? null);

            if (! $subscription) {
                if (! $checkout) {
                    return;
                }

                Subscription::query()
                    ->where('tenant_id', $checkout->tenant_id)
                    ->whereIn('status', ['active', 'trial', 'past_due', 'paused'])
                    ->update(['status' => 'cancelled', 'ends_at' => now()]);

                $subscription = Subscription::query()->create([
                    'tenant_id' => $checkout->tenant_id,
                    'plan_id' => $checkout->plan_id,
                    'status' => $localStatus,
                    'starts_at' => $startsAt,
                    'ends_at' => $nextBillingAt,
                    'provider' => 'mercadopago',
                    'provider_id' => $providerId,
                    'provider_status' => $providerStatus,
                    'external_reference' => $externalReference ?: $checkout->external_reference,
                    'next_billing_at' => $nextBillingAt,
                    'provider_data' => $remote,
                ]);
            } else {
                $subscription->update([
                    'status' => $localStatus,
                    'provider_status' => $providerStatus,
                    'starts_at' => $startsAt,
                    'ends_at' => $nextBillingAt ?? $subscription->ends_at,
                    'next_billing_at' => $nextBillingAt,
                    'provider_data' => $remote,
                ]);
            }

            if ($checkout) {
                $checkout->update([
                    'status' => $providerStatus,
                    'completed_at' => $localStatus === 'active' ? now() : $checkout->completed_at,
                    'provider_data' => $remote,
                ]);
            }

            $tenant = Tenant::query()->find($subscription->tenant_id);
            $tenant?->forceFill([
                'billing_provider' => 'mercadopago',
                'provider_customer_id' => isset($remote['payer_id']) ? $this->stringValue($remote['payer_id']) : null,
                'payment_method_type' => $remote['payment_method_id'] ?? null,
            ])->save();
        });
    }

    /** @param array<string, mixed> $remote */
    private function syncAuthorizedPayment(array $remote): void
    {
        $preapprovalId = $this->stringValue($remote['preapproval_id'] ?? null);
        $remoteId = $this->stringValue($remote['id'] ?? null);

        if ($preapprovalId === '' || $remoteId === '') {
            return;
        }

        $subscription = Subscription::query()->where('provider_id', $preapprovalId)->first();

        if (! $subscription) {
            return;
        }

        $providerStatus = $this->stringValue($remote['status'] ?? null) ?: 'pending';
        $paymentStatus = in_array($providerStatus, ['approved', 'processed'], true)
            ? 'completed'
            : (in_array($providerStatus, ['rejected', 'cancelled', 'canceled'], true) ? 'failed' : 'pending');

        Payment::query()->updateOrCreate(
            ['subscription_id' => $subscription->id, 'transaction_id' => $remoteId],
            [
                'amount' => $this->floatValue($remote['transaction_amount'] ?? null),
                'currency' => mb_strtoupper($this->stringValue($remote['currency_id'] ?? null) ?: $this->currency()),
                'method' => 'mercadopago',
                'status' => $paymentStatus,
                'provider_event_id' => 'authorized:'.$remoteId,
                'paid_at' => $this->date($remote['date_created'] ?? null),
                'provider_data' => $remote,
            ],
        );

        $this->syncPreapproval($this->client->getSubscription($preapprovalId));
    }

    /** @param array<string, mixed> $remote */
    private function syncPayment(array $remote): void
    {
        $metadata = isset($remote['metadata']) && is_array($remote['metadata']) ? $remote['metadata'] : [];
        $preapprovalId = $this->stringValue($remote['preapproval_id'] ?? $metadata['preapproval_id'] ?? null);
        $externalReference = $this->stringValue($remote['external_reference'] ?? null);
        $subscription = $preapprovalId !== ''
            ? Subscription::query()->where('provider_id', $preapprovalId)->first()
            : null;

        if (! $subscription && $externalReference !== '') {
            $subscription = Subscription::query()->where('external_reference', $externalReference)->first();
        }

        if (! $subscription || empty($remote['id'])) {
            return;
        }

        $providerStatus = $this->stringValue($remote['status'] ?? null) ?: 'pending';
        $paymentStatus = $providerStatus === 'approved'
            ? 'completed'
            : (in_array($providerStatus, ['rejected', 'cancelled', 'canceled', 'refunded', 'charged_back'], true) ? 'failed' : 'pending');
        $transactionId = $this->stringValue($remote['id']);

        Payment::query()->updateOrCreate(
            ['subscription_id' => $subscription->id, 'transaction_id' => $transactionId],
            [
                'amount' => $this->floatValue($remote['transaction_amount'] ?? null),
                'currency' => mb_strtoupper($this->stringValue($remote['currency_id'] ?? null) ?: $this->currency()),
                'method' => 'mercadopago',
                'status' => $paymentStatus,
                'provider_event_id' => 'payment:'.$transactionId,
                'paid_at' => $this->date($remote['date_approved'] ?? $remote['date_created'] ?? null),
                'provider_data' => $remote,
            ],
        );

        if ($paymentStatus === 'failed') {
            $subscription->update(['status' => 'past_due']);
        }
    }

    private function mapSubscriptionStatus(string $status): string
    {
        return match ($status) {
            'authorized' => 'active',
            'paused' => 'paused',
            'cancelled', 'canceled' => 'cancelled',
            'pending' => 'pending',
            default => 'pending',
        };
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

    private function currency(): string
    {
        $currency = config('mercadopago.currency', 'PEN');

        return is_string($currency) ? $currency : 'PEN';
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }

    private function floatValue(mixed $value): float
    {
        return is_numeric($value) ? (float) $value : 0.0;
    }
}
