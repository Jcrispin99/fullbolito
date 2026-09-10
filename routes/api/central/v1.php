<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Central\V1\ModuleController;
use App\Http\Controllers\Api\Central\V1\MercadoPagoWebhookController;
use App\Http\Controllers\Api\Central\V1\PlanController;
use App\Http\Controllers\Api\Central\V1\PublicMarketplaceController;
use App\Http\Controllers\Api\Central\V1\PublicUbigeoController;
use App\Http\Controllers\Api\Central\V1\SubscriptionController;
use App\Http\Controllers\Api\Central\V1\TenantController;
use App\Http\Controllers\Api\Central\V1\TenantRegistrationController;
use App\Http\Controllers\Api\Central\V1\ActivityController;
use App\Http\Controllers\Api\Central\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API V1 Routes
|--------------------------------------------------------------------------
|
| Rutas API versionadas SOLO para el dominio central (fullbolito.test).
| Estas rutas NO están disponibles en los subdominios de tenants.
|
*/

Route::prefix('v1')->group(function () {

    Route::post('webhooks/mercadopago', MercadoPagoWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('central.webhooks.mercadopago');

    Route::post('register-tenant', [TenantRegistrationController::class, 'register'])->name('central.tenants.register');

    // Permitir ver planes públicamente (para landing page)
    Route::get('plans', [PlanController::class, 'index'])->name('central.plans.index');
    Route::get('plans/{plan}', [PlanController::class, 'show'])->name('central.plans.show');

    // Marketplace público: agrega canchas de todos los tenants.
    // Estrategia v1: iteración en vivo (Opción A). Cuando escale → cache central.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('marketplace/courts', [PublicMarketplaceController::class, 'courts'])
            ->name('central.marketplace.courts');

        // Catálogo INEI público (para selectores cascade del marketplace).
        // URIs bajo `marketplace/` para no chocar con las rutas ubigeo del
        // tenant (que viven en el mismo `api/v1/` prefix y serían registradas
        // después, sobrescribiendo estas).
        Route::get('marketplace/ubigeo/departments', [PublicUbigeoController::class, 'departments'])
            ->name('central.marketplace.ubigeo.departments');
        Route::get('marketplace/ubigeo/departments/{department}/provinces', [PublicUbigeoController::class, 'provinces'])
            ->name('central.marketplace.ubigeo.provinces');
        Route::get('marketplace/ubigeo/provinces/{province}/districts', [PublicUbigeoController::class, 'districts'])
            ->name('central.marketplace.ubigeo.districts');
        Route::get('marketplace/ubigeo/resolve/{code}', [PublicUbigeoController::class, 'resolve'])
            ->name('central.marketplace.ubigeo.resolve');
    });

    // Gestión autenticada
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('activity', [ActivityController::class, 'index'])->name('central.activity.index');

        // Endpoint para ver MIS tenants (cualquier usuario)
        Route::get('my-tenants', [TenantController::class, 'myTenants'])->name('central.my-tenants');

        // Endpoints de administración de Usuarios
        Route::get('users', [UserController::class, 'index'])->name('central.users.index');
        Route::post('users', [UserController::class, 'store'])->name('central.users.store');
        Route::get('users/{user}', [UserController::class, 'show'])->name('central.users.show');
        Route::put('users/{user}', [UserController::class, 'update'])->name('central.users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('central.users.destroy');

        // Endpoints de administración de Tenants
        Route::get('tenants', [TenantController::class, 'index'])->name('central.tenants.index');
        Route::post('tenants', [TenantController::class, 'store'])->name('central.tenants.store');
        Route::get('tenants/{tenant}', [TenantController::class, 'show'])->name('central.tenants.show');
        Route::put('tenants/{tenant}', [TenantController::class, 'update'])->name('central.tenants.update');
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy'])->name('central.tenants.destroy');

        // Renovación manual de suscripción (Superadmin)
        Route::post('tenants/{tenant}/renew', [SubscriptionController::class, 'renew'])->name('central.tenants.renew');

        // Listado global de suscripciones (Superadmin)
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('central.subscriptions.index');

        // Gestión de Planes (Solo Superadmin - validado en controlador)
        Route::post('plans/batch-delete', [PlanController::class, 'batchDestroy'])->name('central.plans.batch-delete');
        Route::post('plans', [PlanController::class, 'store'])->name('central.plans.store');
        Route::put('plans/{plan}', [PlanController::class, 'update'])->name('central.plans.update');
        Route::patch('plans/{plan}/toggle-status', [PlanController::class, 'toggleStatus'])->name('central.plans.toggle-status');
        Route::delete('plans/{plan}', [PlanController::class, 'destroy'])->name('central.plans.destroy');

        // Gestión de Módulos (Solo Superadmin - validado en controlador)
        Route::get('modules', [ModuleController::class, 'index'])->name('central.modules.index');
        Route::post('modules/batch-delete', [ModuleController::class, 'batchDestroy'])->name('central.modules.batch-delete');
        Route::post('modules', [ModuleController::class, 'store'])->name('central.modules.store');
        Route::get('modules/{module}', [ModuleController::class, 'show'])->name('central.modules.show');
        Route::put('modules/{module}', [ModuleController::class, 'update'])->name('central.modules.update');
        Route::patch('modules/{module}/toggle-status', [ModuleController::class, 'toggleStatus'])->name('central.modules.toggle-status');
        Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('central.modules.destroy');
    });
});
