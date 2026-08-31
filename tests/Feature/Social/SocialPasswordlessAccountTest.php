<?php

declare(strict_types=1);

/**
 * Social accounts with a null password and their password reset escape hatch.
 */

namespace Tests\Feature\Social;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('password login on a social account explains itself', function () {
    // Without the guard, Hash::check('', ...) would report invalid credentials.
    User::factory()->createOne(['email' => 'social@example.com', 'password' => null]);

    $response = post(route('login'), [
        'email' => 'social@example.com',
        'password' => 'qualquer-coisa',
    ]);

    assertGuest();
    $response->assertSessionHasErrors('email');

    expect((string) session('errors')->first('email'))->toContain('login social');
});

it('a social account can set a first password', function () {
    $user = User::factory()->createOne(['password' => null]);

    actingAs($user)
        ->put(route('users.password', ['username' => $user->username]), [
            'password' => 'senha-nova-bem-longa',
            'password_confirmation' => 'senha-nova-bem-longa',
        ])
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    expect(Hash::check('senha-nova-bem-longa', (string) $user->fresh()?->password))->toBeTrue();
});

it('a social account can change its email without a password prompt', function () {
    $user = User::factory()->createOne(['password' => null]);

    actingAs($user)
        ->put(route('users.email', ['username' => $user->username]), [
            'new_email' => 'outro@example.com',
        ])
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    // Still not applied until the link on the new address is clicked -- that is the proof.
    assertDatabaseHas('user_tokens', ['user_id' => $user->id, 'token_type' => 'email_change']);

    expect($user->fresh()?->email)->not->toBe('outro@example.com');
});

it('a social account can request deletion without a password prompt', function () {
    $user = User::factory()->createOne(['password' => null]);

    actingAs($user)
        ->post(route('users.delete-account', ['username' => $user->username]))
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    assertDatabaseHas('user_tokens', ['user_id' => $user->id, 'token_type' => 'account_deletion']);
});

it('password reset still works and is the documented fallback', function () {
    $user = User::factory()->createOne(['email' => 'social@example.com', 'password' => null]);

    $token = Password::broker()->createToken($user);

    post(route('password.update'), [
        'token' => $token,
        'email' => 'social@example.com',
        'password' => 'senha-recuperada-longa',
        'password_confirmation' => 'senha-recuperada-longa',
    ]);

    expect(Hash::check('senha-recuperada-longa', (string) $user->fresh()?->password))->toBeTrue();
});

it('an account that already has a password still needs the current one', function () {
    $user = User::factory()->createOne(['password' => 'senha-atual-longa']);

    actingAs($user)
        ->put(route('users.password', ['username' => $user->username]), [
            'password' => 'senha-nova-bem-longa',
            'password_confirmation' => 'senha-nova-bem-longa',
        ])
        ->assertSessionHasErrors('current_password', null, 'updatePassword');

    expect(Hash::check('senha-atual-longa', (string) $user->fresh()?->password))->toBeTrue();
});
