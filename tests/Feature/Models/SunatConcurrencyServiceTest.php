<?php

declare(strict_types=1);

use App\Jobs\SendInvoiceToSunatJob;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SunatConcurrencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeSunatCapacityTenant(int $slots, bool $dedicated = false): Tenant
{
    $plan = Plan::query()->create([
        'name' => "Plan {$slots}",
        'slug' => 'sunat-slots-'.$slots.'-'.bin2hex(random_bytes(3)),
        'price' => 99,
        'duration_days' => 30,
        'is_active' => true,
        'sunat_worker_slots' => $slots,
        'sunat_dedicated_queue' => $dedicated,
    ]);
    $tenant = Tenant::query()->create(['id' => 'slots-'.bin2hex(random_bytes(4))]);

    Subscription::query()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addMonth(),
    ]);

    return $tenant->fresh();
}

it('resolves the SUNAT capacity from the active plan', function (): void {
    $tenant = makeSunatCapacityTenant(4, dedicated: true);

    expect($tenant->getSunatCapacity())->toBe([
        'worker_slots' => 4,
        'dedicated_queue' => true,
    ]);
});

it('allows only the contracted number of simultaneous SUNAT leases', function (): void {
    $tenant = makeSunatCapacityTenant(2);
    $service = app(SunatConcurrencyService::class);

    $first = $service->acquire($tenant, 101);
    $second = $service->acquire($tenant, 102);
    $third = $service->acquire($tenant, 103);

    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($third)->toBeNull()
        ->and($service->activeSlots($tenant))->toBe(2);

    $service->release($first);

    expect($service->acquire($tenant, 103))->not->toBeNull();
});

it('never leases the same document to two workers', function (): void {
    $tenant = makeSunatCapacityTenant(4);
    $service = app(SunatConcurrencyService::class);

    $lease = $service->acquire($tenant, 501);

    expect($lease)->not->toBeNull()
        ->and($service->acquire($tenant, 501))->toBeNull();
});

it('generates a stable dedicated queue name per tenant', function (): void {
    $first = SendInvoiceToSunatJob::dedicatedQueueName('empresa.demo');

    expect($first)->toBe(SendInvoiceToSunatJob::dedicatedQueueName('empresa.demo'))
        ->and($first)->toStartWith('sunat-tenant-empresa-demo-')
        ->not->toBe(SendInvoiceToSunatJob::dedicatedQueueName('otra-empresa'));
});
