<?php

declare(strict_types=1);

/**
 * Tests the access log, written from terminate().
 */

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * The temp log directory for the test currently running; beforeEach() sets it.
 */
function accessDir(?string $set = null): string
{
    static $dir = '';

    if ($set !== null) {
        $dir = $set;
    }

    return $dir;
}

beforeEach(function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orbita-access-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    accessDir($dir);

    config([
        'logging.channels.access.path' => $dir.'/access.log',
        'logging.channels.app.path' => $dir.'/app.log',
        // Keep the emergency fallback out of the developer's real logs.
        'logging.channels.emergency.path' => $dir.'/emergency.log',
        'orbita.logs.access.enabled' => true,
    ]);

    Log::forgetChannel('access');
    Log::forgetChannel('app');
});

afterEach(function () {
    Log::forgetChannel('access');
    Log::forgetChannel('app');

    foreach ((array) glob(accessDir().DIRECTORY_SEPARATOR.'*') as $file) {
        @unlink((string) $file);
    }
    @rmdir(accessDir());
});

/**
 * @return list<array<string, mixed>>
 */
function accessLines(): array
{
    $lines = [];

    foreach ((array) glob(accessDir().DIRECTORY_SEPARATOR.'access-*.log') as $file) {
        foreach (explode("\n", trim((string) file_get_contents((string) $file))) as $line) {
            if ($line !== '') {
                $lines[] = (array) json_decode($line, true);
            }
        }
    }

    return $lines;
}

it('records one json line per request', function () {
    get('/')->assertOk();

    $lines = accessLines();
    expect($lines)->toHaveCount(1);

    $context = $lines[0]['context'];
    expect($context['method'])->toBe('GET')
        ->and($context['uri'])->toBe('/')
        ->and($context['status'])->toBe(200)
        ->and($context['route'])->toBe('home')
        ->and($context['user_id'])->toBeNull();

    expect($context['duration_ms'] + 0.0)->toBeFloat();
    expect($context['duration_ms'])->toBeGreaterThanOrEqual(0);
});

/**
 * The handle()-side capture survives container re-resolution in terminate().
 */
it('records the authenticated user', function () {
    $user = User::factory()->createOne();

    actingAs($user)->get('/')->assertOk();

    expect(accessLines()[0]['context']['user_id'])->toBe($user->id);
});

it('excluded paths are not recorded', function () {
    get('/up');

    expect(accessLines())->toBe([]);
});

it('records nothing when disabled', function () {
    config(['orbita.logs.access.enabled' => false]);

    get('/')->assertOk();

    expect(accessLines())->toBe([]);
});

it('sensitive query values are redacted', function () {
    get('/?signature=deadbeef&page=2');

    $uri = accessLines()[0]['context']['uri'];

    expect($uri)->not->toContain('deadbeef');
    expect($uri)->toContain('signature=%5Bredacted%5D')->toContain('page=2');
});

it('every request gets a request id', function () {
    get('/')->assertOk();

    expect(accessLines()[0]['context']['request_id'])->toMatch('/^[0-9a-f-]{36}$/');
});

/**
 * Application log lines carry the same request_id as the access line.
 */
it('application lines carry the same request id as the access line', function () {
    Route::get('/__log-probe', function () {
        Log::channel('app')->info('probe line');

        return 'ok';
    });

    get('/__log-probe')->assertOk();

    $requestId = accessLines()[0]['context']['request_id'];
    expect($requestId)->not->toBeEmpty();

    $appLog = '';
    foreach ((array) glob(accessDir().DIRECTORY_SEPARATOR.'app-*.log') as $file) {
        $appLog .= (string) file_get_contents((string) $file);
    }

    expect($appLog)->toContain('probe line')->toContain($requestId);
});

/**
 * A broken access channel must never turn a served request into a 500.
 */
it('a broken access channel does not break the request', function () {
    config(['logging.channels.access' => ['driver' => 'does-not-exist']]);
    Log::forgetChannel('access');

    get('/')->assertOk();
});
