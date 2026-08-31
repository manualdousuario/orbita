<?php

declare(strict_types=1);

namespace Tests\Feature;

use function Pest\Laravel\get;

it('unknown livewire js module is a 404, not a 500', function () {
    get('/livewire/js-module/bookmark-button')->assertNotFound();
});
