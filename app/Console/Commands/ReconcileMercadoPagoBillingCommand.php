<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BillingCheckout;
use App\Models\SubscriptionPlanChange;
use App\Services\MercadoPago\HandleMercadoPagoWebhook;
use App\Services\MercadoPago\MercadoPagoBillingService;
use App\Services\MercadoPago\MercadoPagoClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Recovery path for delayed/lost webhooks and Mercado Pago test payments,
 * which do not always emit real payment notifications.
 */
final class ReconcileMercadoPagoBillingCommand extends Command
{
    protected $signature = 'billing:reconcile-mercadopago {--days=7 : Antigüedad máxima de operaciones pendientes.}';

    protected $description = 'Reconcilia checkouts y cambios de plan pendientes contra Mercado Pago.';

    public function handle(
        MercadoPagoClient $client,
        HandleMercadoPagoWebhook $webhooks,
        MercadoPagoBillingService $billing,
    ): int {
        $cutoff = now()->subDays(max(1, (int) $this->option('days')));
        $failures = 0;
        $checkoutTtl = config('mercadopago.checkout_ttl_hours', 24);
        $checkoutTtlHours = is_int($checkoutTtl) ? max(1, $checkoutTtl) : 24;

        BillingCheckout::query()
            ->where('provider', 'mercadopago')
            ->whereNotNull('provider_id')
            ->whereNull('completed_at')
            ->whereIn('status', ['creating', 'pending', 'authorized'])
            ->where('created_at', '>=', $cutoff)
            ->orderBy('id')
            ->eachById(function (BillingCheckout $checkout) use ($client, $webhooks, &$failures): void {
                try {
                    $webhooks->syncPreapproval($client->getSubscription((string) $checkout->provider_id));
                    $this->line("Checkout #{$checkout->id} reconciliado.");
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("Checkout #{$checkout->id}: {$e->getMessage()}");
                }
            });

        SubscriptionPlanChange::query()
            ->whereIn('status', ['pending_payment', 'paid', 'applying'])
            ->where('created_at', '>=', $cutoff)
            ->orderBy('id')
            ->eachById(function (SubscriptionPlanChange $change) use ($client, $billing, &$failures): void {
                try {
                    if (in_array($change->status, ['paid', 'applying'], true)) {
                        $billing->applyPaidUpgrade($change);
                        $this->line("Upgrade #{$change->id} aplicado.");

                        return;
                    }

                    $search = $client->searchPayments($change->external_reference);
                    $results = isset($search['results']) && is_array($search['results']) ? $search['results'] : [];
                    $payment = collect($results)->first(
                        fn (mixed $item): bool => is_array($item) && ($item['status'] ?? null) === 'approved',
                    ) ?? collect($results)->first(fn (mixed $item): bool => is_array($item));

                    $paymentId = is_array($payment) ? $this->stringValue($payment['id'] ?? null) : '';

                    if ($paymentId !== '') {
                        $billing->recordPlanChangePayment(
                            $change,
                            $client->getPayment($paymentId),
                        );
                        $this->line("Pago del cambio #{$change->id} reconciliado.");
                    }
                } catch (Throwable $e) {
                    $failures++;
                    $this->error("Cambio #{$change->id}: {$e->getMessage()}");
                }
            });

        // Search first, then expire: an approved payment must still win if
        // its webhook arrived close to the checkout expiration boundary.
        SubscriptionPlanChange::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<', now()->subHours($checkoutTtlHours))
            ->update([
                'status' => 'expired',
                'error' => 'El checkout de prorrateo venció sin un pago aprobado.',
            ]);

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
