<?php

declare(strict_types=1);

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $id
 * @property int|null $user_id
 * @property list<string>|null $addon_features
 * @property string|null $billing_provider
 * @property string|null $provider_customer_id
 * @property string|null $payment_method_type
 * @property string|null $payment_method_last_four
 */
final class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Per-request cache for getFeatures(). Reset on hydration.
     *
     * @var array<int, string>|null
     */
    private ?array $featuresCache = null;

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'user_id',
            'addon_features',
            'billing_provider',
            'provider_customer_id',
            'payment_method_type',
            'payment_method_last_four',
            'created_at',
            'updated_at',
        ];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Slug of the active plan (or default plan when no valid subscription).
     */
    public function getPlanSlug(): ?string
    {
        /** @var Subscription|null $subscription */
        $subscription = $this->subscription()->with('plan')->first();

        if ($subscription && $subscription->isValid() && $subscription->plan) {
            return $subscription->plan->slug;
        }

        $defaultPlan = config('saas.default_plan');

        return is_string($defaultPlan) ? $defaultPlan : null;
    }

    /**
     * Features included in the current plan.
     *
     * Resolution order:
     *  1. The active subscription's plan, read from the `plan_module` pivot.
     *     `plans.includes_all_modules = true` expands to every active module.
     *  2. Fallback to `config/saas.php` for slugs without a DB plan row
     *     (used by the test suite's `test-plan`).
     *
     * @return array<int, string>
     */
    public function getPlanFeatures(): array
    {
        $slug = $this->getPlanSlug();

        if (! $slug) {
            return [];
        }

        $plan = Plan::query()->where('slug', $slug)->first();

        if ($plan?->includes_all_modules) {
            return $this->strings(Module::query()->active()->pluck('key')->all());
        }

        $pivotFeatures = $plan
            ? $this->strings($plan->modules()->where('is_active', true)->pluck('key')->all())
            : [];

        if ($pivotFeatures !== []) {
            return $pivotFeatures;
        }

        // Config fallback: used when the slug has no DB plan (e.g. the
        // test-plan provisioned by TenantTestCase) or when a plan exists
        // but its plan_module pivot hasn't been populated yet.
        $features = $this->strings((array) config("saas.plans.{$slug}.features", []));

        if (in_array('*', $features, true)) {
            return $this->strings(Module::query()->active()->pluck('key')->all());
        }

        return $features;
    }

    /**
     * Features the tenant bought as standalone addons.
     *
     * @return array<int, string>
     */
    public function getAddonFeatures(): array
    {
        return $this->strings((array) ($this->addon_features ?? []));
    }

    /**
     * Union of plan features and addon features. Cached per request.
     *
     * @return array<int, string>
     */
    public function getFeatures(): array
    {
        if ($this->featuresCache !== null) {
            return $this->featuresCache;
        }

        return $this->featuresCache = array_values(array_unique(array_merge(
            $this->getPlanFeatures(),
            $this->getAddonFeatures(),
        )));
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->getFeatures(), true);
    }

    public function isFeatureFromPlan(string $key): bool
    {
        return in_array($key, $this->getPlanFeatures(), true);
    }

    public function addAddon(string $key): void
    {
        $current = $this->getAddonFeatures();

        if (! in_array($key, $current, true)) {
            $current[] = $key;
            $this->addon_features = array_values($current);
            $this->save();
            $this->featuresCache = null;
        }
    }

    public function removeAddon(string $key): void
    {
        $current = $this->getAddonFeatures();
        $filtered = array_values(array_filter($current, fn ($k) => $k !== $key));

        if ($filtered !== $current) {
            $this->addon_features = $filtered;
            $this->save();
            $this->featuresCache = null;
        }
    }

    protected function casts(): array
    {
        return [
            'addon_features' => 'array',
        ];
    }

    /**
     * @param  array<mixed>  $values
     * @return list<string>
     */
    private function strings(array $values): array
    {
        return array_values(array_filter($values, is_string(...)));
    }
}
