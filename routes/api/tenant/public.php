<?php

declare(strict_types=1);

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
});
