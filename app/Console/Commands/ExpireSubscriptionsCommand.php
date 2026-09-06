<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Safety-net cron that marks `expired` any active/trial subscription whose
 * period has lapsed without renewal. Provider-backed subs get a grace window
 * because the renewal webhook may arrive with delay.
 */
final class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire
                            {--dry-run : Reportar sin escribir cambios.}
                            {--grace-hours=24 : Horas de gracia para suscripciones de pasarela.}';

    protected $description = 'Marca expired las suscripciones activas/trial cuyo periodo ya venció.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $graceHours = max(0, (int) $this->option('grace-hours'));
        $now = Carbon::now();
        $providerCutoff = $now->copy()->subHours($graceHours);

        $trialIds = Subscription::query()
            ->where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $now)
            ->pluck('id');

        $localActiveIds = Subscription::query()
            ->where('status', 'active')
            ->whereNull('provider_id')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->pluck('id');

        $providerActiveIds = Subscription::query()
            ->where('status', 'active')
            ->whereNotNull('provider_id')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $providerCutoff)
            ->pluck('id');

        $this->info("Trials vencidos: {$trialIds->count()}");
        $this->info("Locales (sin pasarela) vencidos: {$localActiveIds->count()}");
        $this->info("Pasarela vencidos (>{$graceHours}h sin renovar): {$providerActiveIds->count()}");

        $total = $trialIds->count() + $localActiveIds->count() + $providerActiveIds->count();

        if ($dryRun) {
            $this->line("[dry-run] No se aplicaron cambios. Total candidatos: {$total}");

            return self::SUCCESS;
        }

        if ($total === 0) {
            $this->info('Nada que expirar.');

            return self::SUCCESS;
        }

        $allIds = $trialIds->merge($localActiveIds)->merge($providerActiveIds)->all();
        Subscription::query()->whereIn('id', $allIds)->update(['status' => 'expired']);

        $this->info("✔ Total marcadas expired: {$total}");

        return self::SUCCESS;
    }
}
