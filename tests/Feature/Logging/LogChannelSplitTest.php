<?php

declare(strict_types=1);

/**
 * Tests the two-way log split between the app and error files.
 */

namespace Tests\Feature\Logging;

use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\FilterHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\LogRecord;
use ReflectionProperty;

/**
 * The temp log directory for the test currently running; beforeEach() sets it.
 */
function splitDir(?string $set = null): string
{
    static $dir = '';

    if ($set !== null) {
        $dir = $set;
    }

    return $dir;
}

beforeEach(function () {
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'orbita-split-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    splitDir($dir);

    config([
        'logging.channels.app.path' => $dir.'/app.log',
        'logging.channels.error.path' => $dir.'/error.log',
    ]);

    Log::forgetChannel('app');
    Log::forgetChannel('error');
    Log::forgetChannel('stack');
});

afterEach(function () {
    Log::forgetChannel('app');
    Log::forgetChannel('error');
    Log::forgetChannel('stack');

    foreach ((array) glob(splitDir().DIRECTORY_SEPARATOR.'*') as $file) {
        @unlink((string) $file);
    }
    @rmdir(splitDir());
});

function logContents(string $prefix): string
{
    $out = '';

    foreach ((array) glob(splitDir().DIRECTORY_SEPARATOR.$prefix.'-*.log') as $file) {
        $out .= (string) file_get_contents((string) $file);
    }

    return $out;
}

it('info goes only to the app file', function () {
    Log::channel('stack')->info('an informational line');

    expect(logContents('app'))->toContain('an informational line');
    expect(logContents('error'))->not->toContain('an informational line');
});

it('warning and error go only to the error file', function () {
    Log::channel('stack')->warning('a warning line');
    Log::channel('stack')->error('an error line');

    expect(logContents('error'))->toContain('a warning line')->toContain('an error line');

    $app = logContents('app');
    expect($app)->not->toContain('a warning line');
    expect($app)->not->toContain('an error line');
});

/**
 * Notice is the last level that still counts as application noise.
 */
it('notice is the last level that still counts as application noise', function () {
    Log::channel('stack')->notice('a notice line');

    expect(logContents('app'))->toContain('a notice line');
    expect(logContents('error'))->not->toContain('a notice line');
});

it('lines keep the format the single channel produced', function () {
    Log::channel('stack')->info('formatted line', ['k' => 'v']);

    // e.g. [2026-08-03 12:00:00] testing.INFO: formatted line {"k":"v"} []
    expect(logContents('app'))->toMatch('/^\[[\d\-: ]+\] testing\.INFO: formatted line /m');
});

it('placeholders are replaced', function () {
    Log::channel('stack')->info('user {id} signed in', ['id' => 42]);

    expect(logContents('app'))->toContain('user 42 signed in');
});

/**
 * The default stack writes to both files and every handler bubbles.
 */
it('the default stack writes to both files and every handler bubbles', function () {
    expect(config('logging.channels.stack.channels', []))->toBe(
        ['app', 'error'],
        'the default stack changed; the app/error file split may be broken',
    );

    foreach (Log::channel('stack')->getHandlers() as $handler) {
        expect($handler->handle(
            new LogRecord(new DateTimeImmutable, 'testing', Level::Error, 'bubble check')
        ))->toBeFalse($handler::class.' does not bubble');
    }
});

it('the app channel is capped with a filter handler', function () {
    $handlers = Log::channel('app')->getHandlers();

    expect($handlers[0])->toBeInstanceOf(FilterHandler::class);
    expect(array_map(fn ($level) => $level->getName(), $handlers[0]->getAcceptedLevels()))
        ->toBe(['DEBUG', 'INFO', 'NOTICE']);
});

/**
 * Every disk channel rotates daily and keeps the retention window.
 */
it('every disk channel rotates daily and keeps the retention window', function () {
    $expected = (int) env('LOG_RETENTION_DAYS', 14);

    foreach (['app', 'error', 'access'] as $channel) {
        $config = config("logging.channels.{$channel}");

        expect($config['driver'])->toBe('daily', "{$channel} is not on the daily driver");
        expect($config['days'])->toBe($expected, "{$channel} does not honour the retention window");
    }
});

/**
 * The handler underneath is the rotating one, with maxFiles wired to `days`.
 */
it('the retention window reaches the monolog handler', function () {
    $handler = Log::channel('error')->getHandlers()[0];

    expect($handler)->toBeInstanceOf(RotatingFileHandler::class);

    $maxFiles = (new ReflectionProperty(RotatingFileHandler::class, 'maxFiles'))->getValue($handler);

    expect($maxFiles)->toBe((int) env('LOG_RETENTION_DAYS', 14));
});
