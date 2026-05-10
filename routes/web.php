<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Solo para servir el SPA
|--------------------------------------------------------------------------
|
| Estas rutas SOLO sirven el HTML inicial del SPA.
| Toda la lógica y navegación está en Vue Router.
| Toda la comunicación con backend es vía API con Bearer Token.
|
*/

// Central SPA (dominio principal)
foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/{any}', function () {
            return view('central');
        })->where('any', '^(?!docs|api|sanctum|up).*$')->name('central.spa');
    });
}
