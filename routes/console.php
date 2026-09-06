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
| Reservation hold expiration cleanup (multi-tenant)
|--------------------------------------------------------------------------
| Corre `reservations:cleanup-expired-holds` dentro de cada tenant cada
| minuto. Cancela las reservas en estado `held` cuyo `held_until` ya pasó
| (el slot ya estaba libre por el scope currentlyBlocking, esto solo
| limpia el status para que no queden holds zombi en listados/reportes).
*/
Schedule::command('tenants:run reservations:cleanup-expired-holds')
    ->everyMinute()
    ->name('reservations-cleanup-expired-holds')
    ->withoutOverlapping()
    ->onOneServer();

/*
|--------------------------------------------------------------------------
| Subscription expiration safety net (central)
|--------------------------------------------------------------------------
| Cada hora marca `expired` los trials vencidos y, con 24h de gracia, las
| suscripciones de pasarela cuyo `ends_at` pasó sin webhook de renovación.
*/
Schedule::command('subscriptions:expire')
    ->hourly()
    ->name('subscriptions-expire')
    ->withoutOverlapping()
    ->onOneServer();
