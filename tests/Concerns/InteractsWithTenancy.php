<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/**
 * Helpers for tests that run inside a tenant context (TenantTestCase).
 *
 * - Build the X-Company-Ids header, the tenant Host header, the JSON
 *   acceptance header, and the Sanctum token in one call.
 * - Provide a default Company + Warehouse so tests don't need to repeat
 *   that scaffold for every controller test.
 */
trait InteractsWithTenancy
{
    protected ?User $tenantUser = null;

    protected ?Company $defaultCompany = null;

    protected ?Warehouse $defaultWarehouse = null;

    /**
     * Authenticate via Sanctum and remember user for header building.
     *
     * @param  list<int>|null  $companies  IDs sent in `X-Company-Ids` header
     */
    protected function actingAsTenantUser(?User $user = null, ?array $companies = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);
        $this->tenantUser = $user;

        if ($companies !== null) {
            $this->withHeader('X-Company-Ids', implode(',', $companies));
        }

        return $user;
    }

    /**
     * Lazy-create a Company + Warehouse pair for the current tenant.
     *
     * @return array{0: Company, 1: Warehouse}
     */
    protected function setupCompanyAndWarehouse(): array
    {
        if (! $this->defaultCompany) {
            $this->defaultCompany = Company::factory()->create();
            $this->defaultWarehouse = Warehouse::factory()
                ->for($this->defaultCompany)
                ->create();
        }

        return [$this->defaultCompany, $this->defaultWarehouse];
    }

    protected function tenantUrl(string $uri): string
    {
        return 'http://'.$this->tenantHost.'/'.ltrim($uri, '/');
    }

    protected function tenantGetJson(string $uri, array $headers = []): TestResponse
    {
        return $this->getJson($this->tenantUrl($uri), $headers);
    }

    protected function tenantPostJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->postJson($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantPutJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->putJson($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantPatchJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->patchJson($this->tenantUrl($uri), $data, $headers);
    }

    protected function tenantDeleteJson(string $uri, array $data = [], array $headers = []): TestResponse
    {
        return $this->deleteJson($this->tenantUrl($uri), $data, $headers);
    }
}
