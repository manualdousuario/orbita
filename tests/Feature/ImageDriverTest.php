<?php

declare(strict_types=1);

/**
 * libvips is preferred, with a loud fallback to GD when it is unusable.
 */

namespace Tests\Feature;

use App\Support\ImageDriver;
use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Vips\Driver;
use Jcupitt\Vips\Config;

it('make returns vips driver when available', function () {
    expect(ImageDriver::make())->toBeInstanceOf(Driver::class);
    expect(ImageDriver::active())->toBe('vips');
})->skip(fn () => ! ImageDriver::vipsAvailable(), 'libvips is not usable here');

/**
 * Availability is decided by probing the native library, not by inspecting flags.
 */
it('vips availability probes the native library', function () {
    ImageDriver::forgetWarning();

    $prerequisites = class_exists(Driver::class)
        && class_exists(Config::class)
        && extension_loaded('FFI')
        && filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOL);

    $expected = $prerequisites && ImageDriver::vipsVersion() !== null;

    expect(ImageDriver::vipsAvailable())->toBe($expected);
});

it('vips version is null when the native library is absent', function () {
    expect(ImageDriver::vipsVersion())->toBeNull();
    expect(ImageDriver::vipsAvailable())->toBeFalse('flags alone must not declare vips usable');
})->skip(fn () => ImageDriver::vipsVersion() !== null, 'libvips loads here');

it('falls back to gd and warns when vips is unusable', function () {
    Log::spy();
    ImageDriver::forgetWarning();

    expect(ImageDriver::make())->toBeInstanceOf(GdDriver::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'libvips is unusable'))
        ->atLeast()->once();
})->skip(fn () => ImageDriver::vipsAvailable(), 'libvips is usable here; the fallback path cannot be exercised');
