<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Treat the default test host (localhost / 127.0.0.1) as a central
        // domain so the `tenant.or.central` middleware bypasses tenancy
        // initialization on bare TestCase requests.
        config()->set('tenancy.central_domains', array_unique(array_merge(
            (array) config('tenancy.central_domains', []),
            ['localhost', '127.0.0.1'],
        )));
    }
}
