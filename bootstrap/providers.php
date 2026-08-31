<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\SettingsServiceProvider;

return [
    AppServiceProvider::class,
    // Must boot before anything reads config('orbita.*') at request time.
    SettingsServiceProvider::class,
    FortifyServiceProvider::class,
    AdminPanelProvider::class,
];
