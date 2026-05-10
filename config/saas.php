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

    // Fallback when the plan_module pivot is empty. Source of truth is the
    // pivot (see PlanSeeder). Addon-only modules (loyalty, builder,
    // transfers) are intentionally excluded here so they're never granted
    // for free if the pivot is missing — they must always be purchased.
    'plans' => [

        'free-trial' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
        ],

        'basico-mensual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'pos'],
        ],

        'pro-mensual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
        ],

        'enterprise-anual' => [
            'features' => ['dashboard', 'inventory', 'sales', 'purchases', 'pos'],
        ],

        // Used by TenantTestCase — includes addon-only modules so tests
        // can exercise loyalty/builder/transfers without going through the
        // addon purchase flow.
        'test-plan' => [
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
