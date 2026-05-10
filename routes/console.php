<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Lot expiration check (multi-tenant)
|--------------------------------------------------------------------------
| Corre `lots:check-expirations` dentro de cada tenant todos los días a las
| 02:00. Marca lotes vencidos, aplica auto-bloqueo según política y crea
| alertas (deduplicadas por día) para mostrar en la UI.
*/
Schedule::command('tenants:run lots:check-expirations')
    ->dailyAt('02:00')
    ->name('lots-check-expirations')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Subscription expiration safety net (central)
|--------------------------------------------------------------------------
| Cada hora marca `expired` los trials vencidos y, con 24h de gracia, las
| subs Stripe cuyo `ends_at` pasó sin que llegara el webhook de renovación.
*/
Schedule::command('subscriptions:expire')
    ->hourly()
    ->name('subscriptions-expire')
    ->withoutOverlapping()
    ->onOneServer();
