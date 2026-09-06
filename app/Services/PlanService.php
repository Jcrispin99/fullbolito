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
 * Provider-backed changes are handled by their checkout + webhook flow.
 * This service remains for trusted/manual local subscription changes.
 */
final class PlanService
{
    /**
     * Switch the tenant to {@see $newPlan}. Returns the refreshed Tenant.
     *
     * Side effects:
     *   - the latest local subscription's plan_id is updated
     *   - status is reset to "active" and the period is renewed from now()
     *   - any addon whose key is now included in the new plan is removed
     *     from `addon_features` (so the tenant doesn't double-pay)
     */
    public function switchPlan(Tenant $tenant, Plan $newPlan): Tenant
    {
        DB::connection($this->centralConnection())->transaction(function () use ($tenant, $newPlan): void {
            /** @var Subscription|null $subscription */
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

        return $tenant->fresh() ?? $tenant;
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
            $tenant->forceFill(['addon_features' => []]);
            $tenant->save();

            return;
        }

        $planFeatures = $newPlan->modules()->pluck('key')->all();

        // Fall back to config when the plan has no pivot rows AND no wildcard
        // (e.g. unmanaged legacy plans still in config/saas.php).
        if ($planFeatures === []) {
            $planFeatures = array_values(array_filter(
                (array) config("saas.plans.{$newPlan->slug}.features", []),
                is_string(...),
            ));
            if (in_array('*', $planFeatures, true)) {
                $tenant->forceFill(['addon_features' => []]);
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
            $tenant->forceFill(['addon_features' => $kept]);
            $tenant->save();
        }
    }

    private function centralConnection(): string
    {
        $connection = config('tenancy.database.central_connection');

        return is_string($connection) ? $connection : 'mysql';
    }
}
