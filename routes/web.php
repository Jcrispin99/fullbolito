<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Página temporal para probar el login con Google fuera del SPA de Vue.
// TODO: borrar cuando el botón esté integrado en resources/js/central.
Route::get('/google-test', fn () => view('google-test'))->name('google-test');

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
