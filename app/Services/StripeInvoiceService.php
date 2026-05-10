<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

/**
 * Lists Stripe invoices for a tenant. Wraps Cashier's invoice helpers
 * so the controller stays thin and tests can swap this for a fake.
 */
class StripeInvoiceService
{
    /**
     * @return list<array{id: string, number: string|null, total: int, currency: string, status: string|null, created_at: int, hosted_invoice_url: string|null, invoice_pdf: string|null}>
     */
    public function listInvoices(Tenant $tenant): array
    {
        if ($tenant->stripe_id === null) {
            return [];
        }

        return collect($tenant->invoices())
            ->map(fn ($invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number ?? null,
                'total' => (int) ($invoice->total ?? 0),
                'currency' => (string) ($invoice->currency ?? 'usd'),
                'status' => $invoice->status ?? null,
                'created_at' => (int) ($invoice->created ?? 0),
                'hosted_invoice_url' => $invoice->hosted_invoice_url ?? null,
                'invoice_pdf' => $invoice->invoice_pdf ?? null,
            ])
            ->values()
            ->all();
    }
}
