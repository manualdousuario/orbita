<?php

declare(strict_types=1);

/**
 * username is NOT NULL because it backs the /u/{username} profile route.
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('the database rejects a null username', function () {
    User::factory()->createOne(['username' => null]);
})->throws(QueryException::class);

it('the factory gives every user a usable username', function () {
    $user = User::factory()->createOne();

    expect($user->username)->not->toBeNull();
    expect($user->username)->toMatch('/^[a-z0-9_]+$/');
    expect(strlen($user->username))->toBeLessThanOrEqual(50);
});

/** The scenario the NOT NULL guards: an authenticated page must build the profile link. */
it('an authenticated page renders without a url generation error', function () {
    $user = User::factory()->createOne(['email_verified_at' => now()]);

    actingAs($user)->get(route('home'))->assertOk();
});
