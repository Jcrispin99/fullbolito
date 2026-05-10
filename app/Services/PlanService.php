<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Switches a tenant between plans by rebinding its latest subscription.
 *
 * Stripe is mirrored via {@see StripePlanService} when the tenant has a
 * Stripe subscription — that call runs *before* the local DB update so
 * a Stripe failure aborts the whole switch (we never want the local plan
 * advertising a feature the customer isn't being billed for).
 */
final class PlanService
{
    public function __construct(
        private readonly StripePlanService $stripe,
    ) {
    }

    /**
     * Switch the tenant to {@see $newPlan}. Returns the refreshed Tenant.
     *
     * Side effects:
     *   - if the tenant has a Stripe subscription, the matching item's
     *     price is updated to the new plan's stripe_price_id (best-effort:
     *     skipped + logged when there's no Stripe context)
     *   - the latest local subscription's plan_id is updated
     *   - status is reset to "active" and the period is renewed from now()
     *   - any addon whose key is now included in the new plan is removed
     *     from `addon_features` (so the tenant doesn't double-pay)
     */
    public function switchPlan(Tenant $tenant, Plan $newPlan): Tenant
    {
        $oldPlan = $tenant->subscription()->with('plan')->first()?->plan;

        // Sync Stripe FIRST. If it throws, the local DB stays untouched —
        // we'd rather fail the switch than have local features diverge
        // from what Stripe is billing.
        if ($oldPlan) {
            $this->stripe->swapPlan($tenant, $oldPlan, $newPlan);
        }

        DB::connection(config('tenancy.database.central_connection'))->transaction(function () use ($tenant, $newPlan): void {
            $subscription = $tenant->subscription()->first();

            if ($subscription) {
                $subscription->update([
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'starts_at' => Carbon::now(),
                    'ends_at' => Carbon::now()->addDays($newPlan->duration_days),
                ]);
            } else {
                Subscription::create([
                    'tenant_id' => $tenant->id,
                    'plan_id' => $newPlan->id,
                    'status' => 'active',
                    'starts_at' => Carbon::now(),
                    'ends_at' => Carbon::now()->addDays($newPlan->duration_days),
                ]);
            }

            $this->dropRedundantAddons($tenant, $newPlan);
        });

        return $tenant->fresh();
    }

    /**
     * Strip addons that the new plan already includes so the tenant
     * doesn't keep paying for something bundled.
     */
    private function dropRedundantAddons(Tenant $tenant, Plan $newPlan): void
    {
        if ($newPlan->includes_all_modules) {
            // Wildcard plan — every active module is included, so all addons
            // become redundant. Clear the array.
            $tenant->addon_features = [];
            $tenant->save();

            return;
        }

        $planFeatures = $newPlan->modules()->pluck('key')->all();

        // Fall back to config when the plan has no pivot rows AND no wildcard
        // (e.g. unmanaged legacy plans still in config/saas.php).
        if ($planFeatures === []) {
            $planFeatures = (array) config("saas.plans.{$newPlan->slug}.features", []);
            if (in_array('*', $planFeatures, true)) {
                $tenant->addon_features = [];
                $tenant->save();

                return;
            }
        }

        if ($planFeatures === []) {
            return;
        }

        $current = $tenant->getAddonFeatures();
        $kept = array_values(array_filter(
            $current,
            fn (string $addon) => ! in_array($addon, $planFeatures, true),
        ));

        if ($kept !== $current) {
            $tenant->addon_features = $kept;
            $tenant->save();
        }
    }
}
