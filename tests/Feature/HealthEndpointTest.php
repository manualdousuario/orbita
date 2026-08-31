<?php

declare(strict_types=1);

/**
 * /up must fail when either datastore is unreachable: MariaDB, or Valkey (cache, sessions
 * and queue, none of which have a fallback).
 */

namespace Tests\Feature;

use App\Listeners\VerifyDatabaseIsReachable;
use App\Listeners\VerifyValkeyIsReachable;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('up is ok while the database answers', function () {
    get('/up')->assertOk();
});

it('the probe fails loudly when the database is unreachable', function () {
    $original = (string) config('database.default');

    // A port nothing listens on, so the failure is a real connection error and not a mock.
    config(['database.connections.unreachable' => array_merge(
        (array) config('database.connections.'.$original),
        ['host' => '127.0.0.1', 'port' => 1],
    )]);
    config(['database.default' => 'unreachable']);
    DB::purge('unreachable');

    $error = null;

    try {
        (new VerifyDatabaseIsReachable)->handle(new DiagnosingHealth);
    } catch (RuntimeException $e) {
        $error = $e;
    } finally {
        // Undo the swap so teardown doesn't roll back on the dead connection.
        config(['database.default' => $original]);
        DB::purge('unreachable');
    }

    expect($error)->not->toBeNull('the probe must not pass while the database is unreachable');
    expect($error?->getMessage())->toContain('Database unreachable');
});

it('the probe fails loudly when valkey is unreachable', function () {
    config(['database.redis.cache' => array_merge(
        (array) config('database.redis.cache'),
        ['host' => '127.0.0.1', 'port' => 1],
    )]);
    Redis::purge('cache');

    $error = null;

    try {
        (new VerifyValkeyIsReachable)->handle(new DiagnosingHealth);
    } catch (RuntimeException $e) {
        $error = $e;
    } finally {
        Redis::purge('cache');
    }

    expect($error)->not->toBeNull('the probe must not pass while valkey is unreachable');
    expect($error?->getMessage())->toContain('Valkey unreachable');
});

/** The listeners have to be wired, not merely present — events are not auto-discovered here. */
it('both probes are actually registered for the health event', function () {
    $listeners = (array) (app('events')->getRawListeners()[DiagnosingHealth::class] ?? []);

    expect($listeners)->toContain(VerifyDatabaseIsReachable::class)
        ->toContain(VerifyValkeyIsReachable::class);
});
