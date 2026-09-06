<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Tenant\V1\PublicCourtController;
use App\Http\Controllers\Api\Tenant\V1\PublicSiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Public Routes (sin auth, sin subscription check)
|--------------------------------------------------------------------------
*/

Route::prefix('v1/public')->group(function () {
    Route::get('site', [PublicSiteController::class, 'site'])
        ->name('tenant.public.site');
    Route::get('pages', [PublicSiteController::class, 'pages'])
        ->name('tenant.public.pages.index');
    Route::get('pages/{slug}', [PublicSiteController::class, 'page'])
        ->name('tenant.public.pages.show');

    Route::post('forms/submit', [PublicSiteController::class, 'submitForm'])
        ->name('tenant.public.forms.submit');

    // Canchas (sitio público del tenant): catálogo + disponibilidad
    Route::get('courts', [PublicCourtController::class, 'index'])
        ->name('tenant.public.courts.index');
    Route::get('courts/{court}', [PublicCourtController::class, 'show'])
        ->name('tenant.public.courts.show');
    Route::get('courts/{court}/availability', [PublicCourtController::class, 'availability'])
        ->name('tenant.public.courts.availability');
    Route::get('courts/by-slug/{slug}', [PublicCourtController::class, 'showBySlug'])
        ->name('tenant.public.courts.by-slug');

    // Reserva pública (sin auth) — rate-limit estricto para prevenir abuso.
    Route::middleware('throttle:30,1')->group(function () {
        Route::post('courts/{court}/reservations', [PublicCourtController::class, 'storeReservation'])
            ->name('tenant.public.reservations.store');
        Route::get('reservations/{code}', [PublicCourtController::class, 'showReservation'])
            ->name('tenant.public.reservations.show');
    });
});
