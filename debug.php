<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Tenancy
tenancy()->initialize(\App\Models\Tenant::find('tomy'));

try {
    $request = \Illuminate\Http\Request::create('/api/v1/suppliers', 'POST', [
        "is_supplier" => true,
        "document_type" => "RUC",
        "document_number" => "20100000001",
        "business_name" => "Distribuidora Alimentos S.A.C.",
        "email" => "ventas@distalimentos.com",
        "phone" => "01-555-1001",
        "address" => "Av. Principal 123",
        "district" => "Miraflores",
        "province" => "Lima",
        "department" => "Lima",
        "status" => "active",
        "company_id" => 1
    ]);

    $app->make(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
