<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Initialize tenant
$tenant = \App\Models\Tenant::find('tomy') ?? \App\Models\Tenant::first();
if ($tenant) {
    tenancy()->initialize($tenant);
} else {
    echo "No tenant found\n";
    exit(1);
}

$user = \App\Models\User::first();
if (!$user) {
    // Factory might fail if tenant doesn't have required tables, but user is typically seeded
    $user = \App\Models\User::factory()->create();
}
echo $user->createToken('test')->plainTextToken;
