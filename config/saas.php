<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SaaS Plans & Modules
|--------------------------------------------------------------------------
|
| Defines which feature/module keys each plan unlocks. Plan slugs must
| match the `slug` column on the `plans` table (see PlanSeeder). Module
| keys must match the `key` column on the `modules` table (ModuleSeeder).
|
| The special key '*' means "all active modules" — use it for top plans
| so you don't have to update config every time a module is added.
|
| `default_plan` applies when a tenant has no active subscription.
|
*/

return [

    'default_plan' => 'free-trial',

    'sunat' => [
        // Capacidad lógica por tenant. Los procesos físicos pueden seguir
        // compartidos; esta cuota limita cuántos envíos del mismo tenant se
        // ejecutan simultáneamente.
        'default_worker_slots' => 1,
        'max_worker_slots' => 8,
        // Debe ser mayor que el timeout del job (75s) y retry_after (90s).
        'slot_lease_seconds' => 120,
        'slot_release_delay_seconds' => 5,
        'dependency_release_delay_seconds' => 30,
        'dependency_timeout_minutes' => 1440,

        // Al activarlo, los planes marcados como dedicados se enrutan a una
        // cola por tenant. Debe existir un worker escuchando cada una.
        'dedicated_queues_enabled' => (bool) env('SUNAT_DEDICATED_QUEUES_ENABLED', false),
    ],

    // Fallback when the plan_module pivot is empty. Source of truth is the
    // pivot (see PlanSeeder). Addon-only modules (loyalty, builder,
    // transfers) are intentionally excluded here so they're never granted
    // for free if the pivot is missing — they must always be purchased.
    'plans' => [

        'free-trial' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            'sunat_worker_slots' => 1,
            'sunat_dedicated_queue' => false,
        ],

        'basico-mensual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'pos'],
            'sunat_worker_slots' => 1,
            'sunat_dedicated_queue' => false,
        ],

        'pro-mensual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            'sunat_worker_slots' => 2,
            'sunat_dedicated_queue' => false,
        ],

        'enterprise-anual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
            'sunat_worker_slots' => 8,
            'sunat_dedicated_queue' => true,
        ],

        // Used by TenantTestCase — includes addon-only modules so tests
        // can exercise loyalty/builder/transfers without going through the
        // addon purchase flow.
        'test-plan' => [
            'sunat_worker_slots' => 1,
            'sunat_dedicated_queue' => false,
            'features' => [
                'dashboard',
                'inventory',
                'sales',
                'purchases',
                'transfers',
                'pos',
                'loyalty',
                'builder',
            ],
        ],

    ],

];
