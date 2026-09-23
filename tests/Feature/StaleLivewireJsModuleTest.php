<?php

declare(strict_types=1);

namespace Tests\Feature;

use Livewire\Mechanisms\HandleRequests\EndpointResolver;

use function Pest\Laravel\get;

it('unknown livewire module asset is a 404, not a 500', function (string $path) {
    get(EndpointResolver::prefix().$path)->assertNotFound();
})->with([
    'js' => '/js/bookmark-button.js',
    'css' => '/css/bookmark-button.css',
    'global css' => '/css/bookmark-button.global.css',
]);
