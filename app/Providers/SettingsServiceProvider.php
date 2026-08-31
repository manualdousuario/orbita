<?php

namespace App\Providers;

use App\Support\Settings;
use Illuminate\Support\ServiceProvider;

/**
 * Overlays admin-editable settings on config('orbita.*') at boot.
 */
class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Settings::applyToConfig();
    }
}
