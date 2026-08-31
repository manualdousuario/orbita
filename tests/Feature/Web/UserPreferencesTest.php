<?php

declare(strict_types=1);

/**
 * Saved default_comment_sort preferences override the site default.
 */

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attrs
 */
function prefUser(array $attrs = []): User
{
    return User::factory()->createOne(array_merge([
        'username' => 'pref_user',
        'email_verified_at' => now(),
    ], $attrs));
}

it('a user can save a default comment sort', function () {
    $user = prefUser();

    actingAs($user)
        ->put(route('users.update', ['username' => 'pref_user']), [
            'default_comment_sort' => 'most_reactions',
        ])
        ->assertRedirect(route('users.edit', ['username' => 'pref_user']));

    expect($user->refresh()->default_comment_sort)->toBe('most_reactions');
});

it('submitting the empty option clears the saved preference', function () {
    $user = prefUser(['default_comment_sort' => 'oldest']);

    actingAs($user)
        ->put(route('users.update', ['username' => 'pref_user']), [
            'default_comment_sort' => '',
        ])
        ->assertRedirect(route('users.edit', ['username' => 'pref_user']));

    expect($user->refresh()->default_comment_sort)->toBeNull();
});

it('an invalid sort value is rejected', function () {
    $user = prefUser();

    actingAs($user)
        ->put(route('users.update', ['username' => 'pref_user']), [
            'default_comment_sort' => 'bogus',
        ])
        ->assertSessionHasErrors('default_comment_sort');

    expect($user->refresh()->default_comment_sort)->toBeNull();
});

it('a notifications only submission does not touch the saved sort', function () {
    $user = prefUser(['default_comment_sort' => 'oldest']);

    actingAs($user)
        ->put(route('users.update', ['username' => 'pref_user']), [
            'notify_replies_system' => '1',
        ])
        ->assertRedirect(route('users.edit', ['username' => 'pref_user']));

    expect($user->refresh()->default_comment_sort)->toBe('oldest');
});

it('a user can save the followed-post notification preferences', function () {
    $user = prefUser();

    actingAs($user)
        ->put(route('users.update', ['username' => 'pref_user']), [
            'notify_follows_system' => '1',
            'notify_follows_email' => '0',
        ])
        ->assertRedirect(route('users.edit', ['username' => 'pref_user']));

    $user->refresh();

    expect($user->notify_follows_system)->toBeTrue()
        ->and($user->notify_follows_email)->toBeFalse();
});
