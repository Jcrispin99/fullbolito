<?php

declare(strict_types=1);

use Illuminate\Foundation\Vite;
use Stancl\Tenancy\Vite as TenantAwareVite;

it('serves Vite assets from the shared public build', function () {
    $response = $this->get($this->tenantUrl('/login'));

    $response
        ->assertOk()
        ->assertSee('/build/assets/', escape: false)
        ->assertDontSee('/tenancy/assets/build/', escape: false);

    expect(app(Vite::class))->toBeInstanceOf(TenantAwareVite::class);
});
