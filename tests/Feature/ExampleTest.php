<?php

declare(strict_types=1);

/**
 * Default Laravel smoke test.
 */

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('the application returns a successful response', function () {
    get('/')->assertStatus(200);
});
