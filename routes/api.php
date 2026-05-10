<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes are versioned using grazulex/laravel-apiroute v2.x.
| Versions are defined in config/apiroute.php and route files are
| located in routes/api/{version}.php
|
| Supports URI path, header, query, and Accept header detection.
| See config/apiroute.php for configuration options.
|
*/

// ============================================
// RUTAS SIN VERSIONAR - COMPARTIDAS
// ============================================
// Auth completo que funciona tanto en CENTRAL como en TENANT
// El middleware de tenancy cambia automáticamente la BD según el contexto

// Public routes with auth rate limiter (5/min - brute force protection)
Route::middleware(['tenant.or.central', 'throttle:auth'])->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
});

// Protected routes with authenticated rate limiter (120/min)
Route::middleware(['tenant.or.central', 'auth:sanctum', 'throttle:authenticated'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me');

    // Email verification
    Route::post('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

// Password reset routes (public with rate limiting)
Route::middleware(['tenant.or.central', 'throttle:6,1'])->group(function () {
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->name('password.reset');
});

// ============================================
// RUTAS SOLO PARA CENTRAL
// ============================================
// Estas rutas SOLO están disponibles en el dominio central (saas_restop.test)

// Las rutas centrales se protegen via config('tenancy.central_domains')
// que impide la inicialización de tenancy en esos dominios.
// No se usa foreach+domain() para evitar registrar rutas con nombres duplicados.
require base_path('routes/api/central/v1.php');

// ============================================
// RUTAS VERSIONADAS AUTOMÁTICAS
// ============================================
// Las rutas en routes/api/v1.php se cargan automáticamente
// por el sistema de versionado (config/apiroute.php)
// Estas rutas están disponibles tanto en CENTRAL como en TENANT
