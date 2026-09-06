<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\Tenant;

final class MercadoPagoInvoiceService
{
    /** @return list<array<string, mixed>> */
    public function listPayments(Tenant $tenant): array
    {
        $subscriptionIds = $tenant->subscriptions()->pluck('id');

        return array_values(\App\Models\Payment::query()
            ->whereIn('subscription_id', $subscriptionIds)
            ->where('method', 'mercadopago')
            ->latest('paid_at')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn ($payment) => [
                'id' => (string) $payment->id,
                'reference' => $payment->transaction_id,
                'amount' => (float) $payment->amount,
                'currency' => mb_strtoupper((string) $payment->currency),
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])
            ->values()
            ->all());
    }
}
