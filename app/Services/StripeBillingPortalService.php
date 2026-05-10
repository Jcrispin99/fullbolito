<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;

/**
 * Generates Stripe Customer Portal URLs for self-service billing.
 *
 * Wraps Cashier's billingPortalUrl() so the controller stays thin and
 * tests can swap this for a fake via the container.
 */
class StripeBillingPortalService
{
    public function getPortalUrl(Tenant $tenant, string $returnUrl): string
    {
        return $tenant->billingPortalUrl($returnUrl);
    }
}
