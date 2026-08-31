<?php

declare(strict_types=1);

/**
 * Tests the throttles Fortify applies to its costly endpoints.
 */

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('reg:ip:127.0.0.1');
});

it('registration is capped per ip', function () {
    $status = null;

    // The limiter allows 5 per hour; the 6th must be refused.
    for ($i = 0; $i < 6; $i++) {
        $status = post('/register', [
            'username' => 'novato'.$i,
            'email' => "novato{$i}@example.com",
            'password' => 'uma-senha-bem-longa',
            'password_confirmation' => 'uma-senha-bem-longa',
        ])->getStatusCode();
    }

    expect($status)->toBe(429);
});

it('the forgot password endpoint is capped', function () {
    $status = null;

    for ($i = 0; $i < 9; $i++) {
        $status = post('/forgot-password', ['email' => 'alvo@example.com'])->getStatusCode();
    }

    expect($status)->toBe(429);
});

/**
 * Reading the login page is never throttled.
 */
it('reading the login page is never throttled', function () {
    for ($i = 0; $i < 30; $i++) {
        get('/login')->assertOk();
    }
});

/**
 * Fortify's profile and password routes no longer exist.
 */
it('the fortify profile and password routes no longer exist', function () {
    expect(app('router')->has('user-profile-information.update'))->toBeFalse()
        ->and(app('router')->has('user-password.update'))->toBeFalse();
});
