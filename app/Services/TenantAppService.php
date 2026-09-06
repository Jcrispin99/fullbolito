<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Builds the payload consumed by the SPA's "Apps / Plans" page.
 *
 * Returns the catalog of every active module, flagged according to the
 * tenant's current plan + addons, plus the available plans (for upgrade
 * prompts) and totals.
 */
final class TenantAppService
{
    /**
     * @return array{
     *   apps: list<array<string, mixed>>,
     *   current_plan: array<string, mixed>|null,
     *   plans: list<array<string, mixed>>,
     *   addon_total: float,
     *   has_payment_subscription: bool,
     * }
     */
    public function getAppsData(Tenant $tenant): array
    {
        $modules = Module::query()->active()->ordered()->get();

        $planFeatures = $tenant->getPlanFeatures();
        $addonFeatures = $tenant->getAddonFeatures();

        $apps = array_values($modules->map(function (Module $module) use ($planFeatures, $addonFeatures) {
            $includedInPlan = in_array($module->key, $planFeatures, true);
            $isActiveAddon = in_array($module->key, $addonFeatures, true);

            return [
                'key' => $module->key,
                'label' => $module->label,
                'description' => $module->description,
                'icon' => $module->icon,
                'addon_price' => (float) $module->addon_price,
                'enabled' => $includedInPlan || $isActiveAddon,
                'included_in_plan' => $includedInPlan,
                'is_addon' => $module->isAddon(),
                'is_active_addon' => $isActiveAddon,
                'can_toggle' => $module->isAddon() && ! $includedInPlan,
            ];
        })->values()->all());

        $addonTotal = array_sum(array_map(
            fn (array $app) => $app['is_active_addon'] ? $app['addon_price'] : 0,
            $apps,
        ));

        /** @var Subscription|null $subscription */
        $subscription = $tenant->subscription()->with('plan')->first();
        $currentPlan = $subscription && $subscription->plan ? [
            'name' => $subscription->plan->name,
            'slug' => $subscription->plan->slug,
            'price' => (float) $subscription->plan->price,
            'duration_days' => $subscription->plan->duration_days,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ] : null;

        $plans = array_values(Plan::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get()
            ->map(fn (Plan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'duration_days' => $plan->duration_days,
                'modules' => $this->modulesForPlan($plan->slug, $modules),
            ])
            ->values()
            ->all());

        return [
            'apps' => $apps,
            'current_plan' => $currentPlan,
            'plans' => $plans,
            'addon_total' => round($addonTotal, 2),
            'has_payment_subscription' => $subscription?->provider === 'mercadopago'
                && $subscription->provider_id !== null,
        ];
    }

    /**
     * Resolve the module list displayed for a plan card. The `includes_all_modules`
     * flag expands to every active module; otherwise we read the plan_module pivot.
     * Falls back to config/saas.php when the slug has no DB plan (test-plan).
     *
     * @param  \Illuminate\Support\Collection<int, Module>  $modules
     * @return list<array<string, mixed>>
     */
    private function modulesForPlan(string $slug, $modules): array
    {
        $plan = Plan::query()->where('slug', $slug)->first();

        if ($plan?->includes_all_modules) {
            $keys = $modules->pluck('key')->all();
        } else {
            $keys = $plan ? $plan->modules()->pluck('key')->all() : [];

            if ($keys === []) {
                $features = (array) config("saas.plans.{$slug}.features", []);
                $keys = in_array('*', $features, true)
                    ? $modules->pluck('key')->all()
                    : $features;
            }
        }

        return array_values($modules
            ->filter(fn (Module $m) => in_array($m->key, $keys, true))
            ->map(fn (Module $m) => [
                'key' => $m->key,
                'label' => $m->label,
                'icon' => $m->icon,
            ])
            ->values()
            ->all());
    }
}
