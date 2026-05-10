<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    // Tenant SPA (subdominios)
    Route::get('/{any}', function () {
        return view('tenant');
    })->where('any', '^(?!api).*$')->name('tenant.spa');
});

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {

    // ============================================
    // RUTAS PÚBLICAS DEL SITIO (sin auth, sin subscription check)
    // ============================================
    Route::prefix('api')->group(function () {
        require base_path('routes/api/tenant/public.php');
    });

    // ============================================
    // RUTAS VERSIONADAS PARA TENANT
    // ============================================
    Route::prefix('api')->middleware('subscription.check')->group(function () {

        // Endpoint de prueba para verificar tenancy
        Route::get('/v1/tenant-info', function () {
            return response()->json([
                'tenant_id' => tenant('id'),
                'database' => app(Illuminate\Database\DatabaseManager::class)
                    ->connection('tenant')->getDatabaseName(),
                'users_count' => App\Models\User::count(),
                'context' => 'tenant',
            ]);
        });

        // Incluir rutas versionadas específicas del tenant
        require base_path('routes/api/tenant/v1.php');

    });

});
