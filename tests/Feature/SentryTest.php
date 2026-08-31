<?php

declare(strict_types=1);

namespace Tests\Feature;

use Sentry\Laravel\ServiceProvider;
use Sentry\State\HubInterface;

it('registers the Sentry service provider', function () {
    expect(app()->providerIsLoaded(ServiceProvider::class))->toBeTrue();
});

it('resolves the hub with the configured DSN', function () {
    config(['sentry.dsn' => 'https://public@example.com/1']);
    app()->forgetInstance(HubInterface::class);

    $dsn = app(HubInterface::class)->getClient()?->getOptions()->getDsn();

    expect($dsn)->not->toBeNull()
        ->and($dsn->getHost())->toBe('example.com')
        ->and($dsn->getProjectId())->toBe('1');
});

it('stays inert when no DSN is configured', function () {
    config(['sentry.dsn' => null]);
    app()->forgetInstance(HubInterface::class);

    expect(app(HubInterface::class)->getClient()?->getOptions()->getDsn())->toBeNull();
});
