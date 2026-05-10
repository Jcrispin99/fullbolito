<?php

declare(strict_types=1);

use Tests\TenantTestCase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case Bindings
|--------------------------------------------------------------------------
|
| Central tests (auth, central-only routes) bind to TestCase.
| Tenant tests bind to TenantTestCase, which:
|  - migrates the central DB (tenants, domains, users-central)
|  - creates a fresh tenant + SQLite file
|  - runs tenant migrations
|  - initializes tenancy
|
| Tenant suites live under tests/Feature/Api/Tenant/, tests/Feature/Tenant/,
| or tests/Unit/Tenant/.
*/

pest()->extend(TenantTestCase::class)->in(
    'Feature/Api/Tenant',
    'Feature/Tenant',
    'Unit/Tenant',
);

pest()->extend(TestCase::class)->in('Feature/Api/V1', 'Feature/Models', 'Feature/Listeners', 'Feature/Console');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeOne', fn () => $this->toBe(1));

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function something(): void
{
    // ..
}
