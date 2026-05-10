<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Services\GreenterDespatchService;
use App\Services\GreenterInvoiceService;
use Tests\Doubles\FakeGreenterDespatchService;
use Tests\Doubles\FakeGreenterInvoiceService;

/**
 * Bind in-memory replacements for the Greenter services. These never hit
 * SUNAT, never read certificates, never write files. Tests can flip
 * `shouldSucceed` to test the failure paths.
 */
trait MocksGreenter
{
    protected ?FakeGreenterInvoiceService $fakeInvoiceService = null;

    protected ?FakeGreenterDespatchService $fakeDespatchService = null;

    protected function mockGreenterInvoice(bool $shouldSucceed = true): FakeGreenterInvoiceService
    {
        $fake = new FakeGreenterInvoiceService();
        $fake->shouldSucceed = $shouldSucceed;
        $this->app->instance(GreenterInvoiceService::class, $fake);
        $this->fakeInvoiceService = $fake;

        return $fake;
    }

    protected function mockGreenterDespatch(bool $shouldSucceed = true): FakeGreenterDespatchService
    {
        $fake = new FakeGreenterDespatchService();
        $fake->shouldSucceed = $shouldSucceed;
        $this->app->instance(GreenterDespatchService::class, $fake);
        $this->fakeDespatchService = $fake;

        return $fake;
    }
}
