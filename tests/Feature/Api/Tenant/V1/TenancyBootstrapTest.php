<?php

declare(strict_types=1);

use App\Models\User;

it('boots a tenant database on setUp', function () {
    expect($this->tenant)->not->toBeNull();
    expect(tenancy()->initialized)->toBeTrue();
    expect(tenant('id'))->toBe($this->tenant->id);
});

it('isolates user creation in the tenant database', function () {
    User::factory()->create(['email' => 'tenant-user@example.com']);

    expect(User::where('email', 'tenant-user@example.com')->exists())->toBeTrue();
});
