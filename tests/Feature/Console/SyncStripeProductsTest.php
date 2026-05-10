<?php

declare(strict_types=1);

use App\Models\Module;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\StripeClient;

uses(RefreshDatabase::class);

/**
 * Drop-in StripeClient stub. Records every Products/Prices call so the
 * test can assert what was sent without ever hitting Stripe.
 */
class FakeStripeClient extends StripeClient
{
    public array $calls = [];

    public object $products;

    public object $prices;

    public function __construct()
    {
        parent::__construct(['api_key' => 'sk_test_fake']);

        $self = $this;

        $this->products = new class($self)
        {
            private int $seq = 0;

            public function __construct(private FakeStripeClient $parent) {}

            public function create(array $data): object
            {
                $this->parent->calls[] = ['type' => 'product.create', 'data' => $data];
                $id = 'prod_test_'.++$this->seq;

                return (object) ['id' => $id];
            }
        };

        $this->prices = new class($self)
        {
            private int $seq = 0;

            public function __construct(private FakeStripeClient $parent) {}

            public function create(array $data): object
            {
                $this->parent->calls[] = ['type' => 'price.create', 'data' => $data];
                $id = 'price_test_'.++$this->seq;

                return (object) ['id' => $id];
            }
        };
    }
}

beforeEach(function (): void {
    $this->fake = new FakeStripeClient();
    $this->app->instance(StripeClient::class, $this->fake);
});

it('creates Stripe product+price for billable plans and writes IDs back', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Pro Mensual', 'slug' => 'pro-mensual',
        'price' => 59.99, 'duration_days' => 30, 'is_active' => true,
    ]);

    $this->artisan('stripe:sync-products', ['--plans' => true])->assertExitCode(0);

    $plan->refresh();
    expect($plan->stripe_product_id)->toStartWith('prod_test_');
    expect($plan->stripe_price_id)->toStartWith('price_test_');

    $price = collect($this->fake->calls)->firstWhere('type', 'price.create');
    expect($price['data'])
        ->toMatchArray([
            'unit_amount' => 5999,
            'currency' => 'pen',
        ])
        ->and($price['data']['recurring'])->toBe(['interval' => 'month', 'interval_count' => 1]);
});

it('creates yearly Stripe price for 365-day plans', function (): void {
    Plan::query()->create([
        'name' => 'Enterprise', 'slug' => 'enterprise',
        'price' => 499.99, 'duration_days' => 365, 'is_active' => true,
    ]);

    $this->artisan('stripe:sync-products', ['--plans' => true])->assertExitCode(0);

    $price = collect($this->fake->calls)->firstWhere('type', 'price.create');
    expect($price['data']['recurring'])->toBe(['interval' => 'year', 'interval_count' => 1]);
});

it('skips plans that already have a stripe_price_id', function (): void {
    Plan::query()->create([
        'name' => 'Already Synced', 'slug' => 'synced',
        'price' => 10, 'duration_days' => 30, 'is_active' => true,
        'stripe_product_id' => 'prod_existing',
        'stripe_price_id' => 'price_existing',
    ]);

    $this->artisan('stripe:sync-products', ['--plans' => true])->assertExitCode(0);

    expect($this->fake->calls)->toBeEmpty();
});

it('--force recreates Price but reuses existing product', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Reprice', 'slug' => 'reprice',
        'price' => 25, 'duration_days' => 30, 'is_active' => true,
        'stripe_product_id' => 'prod_existing',
        'stripe_price_id' => 'price_old',
    ]);

    $this->artisan('stripe:sync-products', ['--plans' => true, '--force' => true])->assertExitCode(0);

    $kinds = collect($this->fake->calls)->pluck('type')->all();
    expect($kinds)->toBe(['price.create']);

    $plan->refresh();
    expect($plan->stripe_product_id)->toBe('prod_existing');
    expect($plan->stripe_price_id)->toStartWith('price_test_');
});

it('skips inactive plans and free plans', function (): void {
    Plan::query()->create([
        'name' => 'Inactive', 'slug' => 'inactive',
        'price' => 10, 'duration_days' => 30, 'is_active' => false,
    ]);
    Plan::query()->create([
        'name' => 'Free', 'slug' => 'free',
        'price' => 0, 'duration_days' => 30, 'is_active' => true,
    ]);

    $this->artisan('stripe:sync-products', ['--plans' => true])->assertExitCode(0);

    expect($this->fake->calls)->toBeEmpty();
});

it('syncs addon modules with monthly recurring price', function (): void {
    $module = Module::factory()->addon(9.99)->create(['key' => 'loyalty', 'label' => 'Fidelización']);

    $this->artisan('stripe:sync-products', ['--modules' => true])->assertExitCode(0);

    $module->refresh();
    expect($module->stripe_price_id)->toStartWith('price_test_');

    $price = collect($this->fake->calls)->firstWhere('type', 'price.create');
    expect($price['data']['unit_amount'])->toBe(999);
    expect($price['data']['recurring'])->toBe(['interval' => 'month', 'interval_count' => 1]);
});

it('skips non-addon modules (addon_price = 0)', function (): void {
    Module::factory()->create(['key' => 'sales', 'addon_price' => 0]);

    $this->artisan('stripe:sync-products', ['--modules' => true])->assertExitCode(0);

    expect($this->fake->calls)->toBeEmpty();
});

it('--dry-run never calls Stripe and never writes IDs', function (): void {
    $plan = Plan::query()->create([
        'name' => 'Dry', 'slug' => 'dry',
        'price' => 10, 'duration_days' => 30, 'is_active' => true,
    ]);

    $this->artisan('stripe:sync-products', ['--dry-run' => true])->assertExitCode(0);

    expect($this->fake->calls)->toBeEmpty();
    expect($plan->fresh()->stripe_price_id)->toBeNull();
});
