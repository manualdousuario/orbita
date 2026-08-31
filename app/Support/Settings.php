<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Reads and writes admin-editable overrides for config('orbita.*').
 */
class Settings
{
    private const CACHE_KEY = 'orbita.settings';

    private const CACHE_TTL = 86400;

    /**
     * Stored overrides cast to their declared types, keyed by config dot-key.
     *
     * @return array<string, mixed>
     */
    public static function overrides(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                if (! Schema::hasTable('settings')) {
                    return [];
                }

                $out = [];
                foreach (Setting::all(['key', 'value']) as $row) {
                    if (SettingsRegistry::isEditable($row->key)) {
                        $out[$row->key] = SettingsRegistry::cast($row->key, $row->value);
                    }
                }

                return $out;
            });
        } catch (Throwable) {
            // No DB yet (e.g. `artisan migrate` on a fresh database) — fall back to config defaults.
            return [];
        }
    }

    /** Current effective value: stored override if present, else the config default. */
    public static function get(string $configKey): mixed
    {
        return self::overrides()[$configKey] ?? config($configKey);
    }

    /**
     * Persists registry-allowed overrides and re-applies them to config.
     *
     * @param  array<string, mixed>  $values  config dot-key => value
     */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! SettingsRegistry::isEditable($key)) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => SettingsRegistry::serialize($key, $value)],
            );
        }

        self::flush();
        self::applyToConfig();
    }

    /** Overlays the stored overrides onto the config repository. Called at boot and after set(). */
    public static function applyToConfig(): void
    {
        foreach (self::overrides() as $key => $value) {
            config([$key => $value]);
        }
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Effective values for every registry key (override or config default). */
    public static function all(): array
    {
        $overrides = self::overrides();

        $out = [];
        foreach (array_keys(SettingsRegistry::definitions()) as $key) {
            $out[$key] = $overrides[$key] ?? config($key);
        }

        return $out;
    }
}
