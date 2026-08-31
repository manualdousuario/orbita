<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refuse to run against anything but a *_test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if ($database !== ':memory:' && ! str_ends_with($database, '_test')) {
            $this->fail(
                "The test database resolved to '{$database}', which does not end in _test — RefreshDatabase "
                .'would wipe it. Almost certainly a bootstrap/cache/config.php is freezing .env over the top '
                .'of phpunit.xml. Run `php artisan config:clear`.'
            );
        }

        // No test may reach the network.
        Http::preventStrayRequests();
    }
}
