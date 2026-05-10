<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\Concerns\InteractsWithTenancy;
use Tests\Concerns\MocksGreenter;

/**
 * Base test case for any test that needs a real tenant context
 * (its own SQLite DB, tenant migrations applied, tenancy initialized).
 *
 * Central tables (tenants, domains, users-central, billing) live on the
 * default in-memory SQLite connection (RefreshDatabase). Each test creates
 * its own tenant + a file-based SQLite DB under `database/`, runs tenant
 * migrations, and tears the file down on teardown.
 */
abstract class TenantTestCase extends TestCase
{
    use InteractsWithTenancy;
    use MocksGreenter;
    use RefreshDatabase;

    protected ?Tenant $tenant = null;

    protected string $tenantHost;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootTenant();
    }

    protected function tearDown(): void
    {
        $this->teardownTenant();

        parent::tearDown();
    }

    protected function bootTenant(): void
    {
        $id = 'test_'.Str::lower(Str::random(10));
        $host = $id.'.'.env('TENANCY_TEST_HOST', 'tenant.test');

        config()->set('tenancy.central_domains', array_unique(array_merge(
            (array) config('tenancy.central_domains', []),
            ['localhost', '127.0.0.1'],
        )));

        // Replace the default TenantCreated pipeline so tests don't run the
        // production DatabaseSeeder (which would conflict with factory data).
        Event::forget(TenantCreated::class);
        Event::listen(
            TenantCreated::class,
            JobPipeline::make([
                CreateDatabase::class,
                MigrateDatabase::class,
            ])->send(fn (TenantCreated $event) => $event->tenant)
              ->shouldBeQueued(false)
              ->toListener(),
        );

        $this->tenant = Tenant::create(['id' => $id]);
        $this->tenant->domains()->create(['domain' => $host]);

        Artisan::call('tenants:migrate', ['--tenants' => [$this->tenant->id]]);

        // Provision an active subscription so the CheckTenantSubscription
        // middleware lets requests through.
        $plan = Plan::firstOrCreate(
            ['slug' => 'test-plan'],
            ['name' => 'Test Plan', 'price' => 0, 'duration_days' => 365, 'is_active' => true],
        );
        Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addYear(),
        ]);

        $this->tenantHost = $host;

        tenancy()->initialize($this->tenant);
    }

    protected function teardownTenant(): void
    {
        if (! $this->tenant) {
            return;
        }

        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        // Tenant DB file lives at database_path('<prefix><id>') (no extension).
        $prefix = mb_strtolower((string) env('APP_NAME', 'tenant')).'-';
        $candidates = [
            database_path($prefix.$this->tenant->id),
            database_path($prefix.$this->tenant->id.'.sqlite'),
        ];

        foreach ($candidates as $path) {
            if (File::exists($path)) {
                File::delete($path);
            }
        }

        $this->tenant = null;
    }
}
