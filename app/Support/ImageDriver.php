<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Vips\Driver;
use Intervention\Image\Interfaces\DriverInterface;
use Jcupitt\Vips\Config;

/**
 * Resolves the intervention/image driver, preferring libvips with a GD fallback.
 */
class ImageDriver
{
    /** Warn once per process (i.e. once per FPM request), not once per image. */
    private static ?bool $vipsWarned = null;

    /** Clears the once-per-process warning latch and the libvips probe. For tests and diagnostics. */
    public static function forgetWarning(): void
    {
        self::$vipsWarned = null;
        self::$vipsProbe = null;
    }

    public static function make(): DriverInterface
    {
        return self::vipsOrFallback();
    }

    private static ?bool $vipsProbe = null;

    /**
     * True when libvips can actually be used right now (probed, not just configured).
     */
    public static function vipsAvailable(): bool
    {
        if (self::$vipsProbe !== null) {
            return self::$vipsProbe;
        }

        $prerequisites = class_exists(Driver::class)
            && class_exists(Config::class)
            && extension_loaded('FFI')
            && filter_var(ini_get('ffi.enable'), FILTER_VALIDATE_BOOL);

        if (! $prerequisites) {
            return self::$vipsProbe = false;
        }

        return self::$vipsProbe = self::vipsVersion() !== null;
    }

    /** libvips version, or null when the native library cannot be loaded. */
    public static function vipsVersion(): ?string
    {
        if (! class_exists(Config::class)) {
            return null;
        }

        try {
            return (string) Config::version();
        } catch (\Throwable) {
            return null;
        }
    }

    public static function imagickAvailable(): bool
    {
        return extension_loaded('imagick');
    }

    /** Driver actually in use (may be GD when the libvips fallback kicked in). */
    public static function active(): string
    {
        $driver = self::make();

        return $driver instanceof GdDriver ? 'gd' : 'vips';
    }

    private static function vipsOrFallback(): DriverInterface
    {
        if (self::vipsAvailable()) {
            /** @var DriverInterface */
            return new Driver;
        }

        if (self::$vipsWarned === null) {
            self::$vipsWarned = true;
            Log::warning('libvips is unusable; falling back to GD.', [
                'driver_class' => class_exists(Driver::class),
                'ffi_loaded' => extension_loaded('FFI'),
                'ffi_enable' => ini_get('ffi.enable'),
                'libvips_version' => self::vipsVersion(),
            ]);
        }

        return new GdDriver;
    }
}
