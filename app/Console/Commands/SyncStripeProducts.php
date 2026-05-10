<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;
use Throwable;

/**
 * Provisions Stripe Product + recurring Price rows for every billable Plan
 * and addon Module, then writes the resulting IDs back to the local DB.
 *
 * Idempotent by design: rows with a stripe_price_id are skipped unless
 * --force is passed (which creates a new Price and rebinds the column —
 * existing Stripe Prices are *not* archived, you'd do that in the dashboard
 * if needed, since archived prices break in-flight subscriptions).
 *
 *   php artisan stripe:sync-products              # sync everything missing IDs
 *   php artisan stripe:sync-products --plans      # plans only
 *   php artisan stripe:sync-products --modules    # addon modules only
 *   php artisan stripe:sync-products --dry-run    # preview without API calls
 *   php artisan stripe:sync-products --force      # re-create Prices for synced rows
 */
final class SyncStripeProducts extends Command
{
    protected $signature = 'stripe:sync-products
        {--plans : Only sync Plan rows}
        {--modules : Only sync addon Module rows}
        {--force : Recreate Prices even when stripe_price_id is already set}
        {--dry-run : Print what would happen without calling Stripe}';

    protected $description = 'Create Stripe Products and recurring Prices for Plans and addon Modules';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $only = match (true) {
            $this->option('plans') => 'plans',
            $this->option('modules') => 'modules',
            default => 'both',
        };

        // Resolve via container so tests can bind a fake StripeClient.
        $stripe = $dryRun ? null : (app()->bound(StripeClient::class)
            ? app(StripeClient::class)
            : Cashier::stripe());
        $currency = strtolower((string) config('cashier.currency', 'usd'));

        if ($only !== 'modules') {
            $this->syncPlans($stripe, $currency, $force, $dryRun);
        }

        if ($only !== 'plans') {
            $this->syncModules($stripe, $currency, $force, $dryRun);
        }

        return self::SUCCESS;
    }

    private function syncPlans(?StripeClient $stripe, string $currency, bool $force, bool $dryRun): void
    {
        $this->info('— Plans —');

        $plans = Plan::query()
            ->where('is_active', true)
            ->where('price', '>', 0)
            ->orderBy('price')
            ->get();

        if ($plans->isEmpty()) {
            $this->line('  (no billable plans)');

            return;
        }

        foreach ($plans as $plan) {
            $this->syncBillable(
                model: $plan,
                stripe: $stripe,
                currency: $currency,
                force: $force,
                dryRun: $dryRun,
                amount: (float) $plan->price,
                interval: $this->intervalFromDuration((int) $plan->duration_days),
                productName: $plan->name,
                metadata: ['slug' => $plan->slug, 'kind' => 'plan'],
            );
        }
    }

    private function syncModules(?StripeClient $stripe, string $currency, bool $force, bool $dryRun): void
    {
        $this->info('— Addon modules —');

        $modules = Module::query()
            ->where('is_active', true)
            ->where('addon_price', '>', 0)
            ->orderBy('sort_order')
            ->get();

        if ($modules->isEmpty()) {
            $this->line('  (no addon modules)');

            return;
        }

        foreach ($modules as $module) {
            $this->syncBillable(
                model: $module,
                stripe: $stripe,
                currency: $currency,
                force: $force,
                dryRun: $dryRun,
                amount: (float) $module->addon_price,
                // Addons bill monthly regardless of the plan's cadence —
                // we don't currently model annual addons.
                interval: ['interval' => 'month', 'interval_count' => 1],
                productName: $module->label,
                metadata: ['key' => $module->key, 'kind' => 'module'],
            );
        }
    }

    /**
     * @param  array{interval: string, interval_count: int}  $interval
     * @param  array<string, string>  $metadata
     */
    private function syncBillable(
        Model $model,
        ?StripeClient $stripe,
        string $currency,
        bool $force,
        bool $dryRun,
        float $amount,
        array $interval,
        string $productName,
        array $metadata,
    ): void {
        $label = $metadata['slug'] ?? $metadata['key'] ?? (string) $model->getKey();
        $hasPrice = $model->stripe_price_id !== null;

        if ($hasPrice && ! $force) {
            $this->line("  [skip] {$label} — already has stripe_price_id={$model->stripe_price_id}");

            return;
        }

        $unitAmount = (int) round($amount * 100);

        if ($dryRun) {
            $action = $hasPrice ? 'recreate' : ($model->stripe_product_id ? 'add price to product' : 'create product+price');
            $this->line("  [dry] {$label} — {$action}: {$unitAmount} {$currency}/{$interval['interval']}");

            return;
        }

        try {
            // Reuse the product if we already created one — Stripe lets
            // multiple Prices hang off the same Product, which is what
            // happens when the user re-prices a plan with --force.
            $productId = $model->stripe_product_id;

            if ($productId === null) {
                $product = $stripe->products->create([
                    'name' => $productName,
                    'metadata' => $metadata,
                ]);
                $productId = $product->id;
            }

            $price = $stripe->prices->create([
                'product' => $productId,
                'unit_amount' => $unitAmount,
                'currency' => $currency,
                'recurring' => $interval,
                'metadata' => $metadata,
            ]);

            $model->forceFill([
                'stripe_product_id' => $productId,
                'stripe_price_id' => $price->id,
            ])->save();

            $this->info("  [ok]   {$label} — product={$productId} price={$price->id}");
        } catch (Throwable $e) {
            $this->error("  [fail] {$label} — {$e->getMessage()}");
        }
    }

    /**
     * @return array{interval: string, interval_count: int}
     */
    private function intervalFromDuration(int $days): array
    {
        // Stripe Recurring intervals: day | week | month | year. Match the
        // common cases exactly so the dashboard reads "Monthly" / "Yearly"
        // rather than "Every 30 days" / "Every 365 days".
        return match (true) {
            $days === 365 || $days === 366 => ['interval' => 'year', 'interval_count' => 1],
            $days === 30 || $days === 31 => ['interval' => 'month', 'interval_count' => 1],
            $days === 7 => ['interval' => 'week', 'interval_count' => 1],
            $days % 365 === 0 => ['interval' => 'year', 'interval_count' => intdiv($days, 365)],
            $days % 30 === 0 => ['interval' => 'month', 'interval_count' => intdiv($days, 30)],
            $days % 7 === 0 => ['interval' => 'week', 'interval_count' => intdiv($days, 7)],
            default => ['interval' => 'day', 'interval_count' => $days],
        };
    }
}
