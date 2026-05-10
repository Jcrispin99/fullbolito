<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Safety-net cron that marks `expired` any active/trial subscription whose
 * period has lapsed without renewal. Stripe-backed subs get a grace window
 * because the renewal webhook may arrive with delay.
 */
final class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire
                            {--dry-run : Reportar sin escribir cambios.}
                            {--grace-hours=24 : Horas de gracia para subs Stripe.}';

    protected $description = 'Marca expired las suscripciones activas/trial cuyo periodo ya venció.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceHours = max(0, (int) $this->option('grace-hours'));
        $now = Carbon::now();
        $stripeCutoff = $now->copy()->subHours($graceHours);

        $trialIds = Subscription::query()
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->pluck('id');

        $localActiveIds = Subscription::query()
            ->where('status', 'active')
            ->whereNull('stripe_id')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->pluck('id');

        $stripeActiveIds = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('stripe_id')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $stripeCutoff)
            ->pluck('id');

        $this->info("Trials vencidos: {$trialIds->count()}");
        $this->info("Locales (sin Stripe) vencidos: {$localActiveIds->count()}");
        $this->info("Stripe vencidos (>{$graceHours}h sin renovar): {$stripeActiveIds->count()}");

        $total = $trialIds->count() + $localActiveIds->count() + $stripeActiveIds->count();

        if ($dryRun) {
            $this->line("[dry-run] No se aplicaron cambios. Total candidatos: {$total}");

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Nada que expirar.');

            return self::SUCCESS;
        }

        $allIds = $trialIds->merge($localActiveIds)->merge($stripeActiveIds)->all();
        Subscription::query()->whereIn('id', $allIds)->update(['status' => 'expired']);

        $this->info("✔ Total marcadas expired: {$total}");

        return self::SUCCESS;
    }
}
