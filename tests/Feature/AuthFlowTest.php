<?php

declare(strict_types=1);

/**
 * Login, ban, password-reset, lockout and registration flows.
 */

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('verified active user with correct password authenticates', function () {
    $user = User::factory()->createOne([
        'email' => 'active@example.com',
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
        'is_banned' => false,
    ]);

    $response = post(route('login'), [
        'email' => 'active@example.com',
        'password' => 'super-secret-pass',
    ]);

    assertAuthenticatedAs($user);
    $response->assertSessionHasNoErrors();

    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

it('wrong password is rejected', function () {
    User::factory()->createOne([
        'email' => 'active@example.com',
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
    ]);

    $response = post(route('login'), [
        'email' => 'active@example.com',
        'password' => 'wrong-password',
    ]);

    assertGuest();
    $response->assertSessionHasErrors('email');
});

it('banned user cannot log in', function () {
    User::factory()->createOne([
        'email' => 'banned@example.com',
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
        'is_banned' => true,
    ]);

    $response = post(route('login'), [
        'email' => 'banned@example.com',
        'password' => 'super-secret-pass',
    ]);

    assertGuest();
    $response->assertSessionHasErrors(['email' => 'Sua conta foi banida']);
});

/**
 * The password-reset path must also enforce the ban check.
 */
it('banned user cannot reset password', function () {
    $user = User::factory()->createOne([
        'email' => 'banned-reset@example.com',
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
        'is_banned' => true,
    ]);

    $response = post(route('password.update'), [
        'token' => Password::broker()->createToken($user),
        'email' => 'banned-reset@example.com',
        'password' => 'uma-senha-bem-longa',
        'password_confirmation' => 'uma-senha-bem-longa',
    ]);

    assertGuest();
    $response->assertSessionHasErrors('email');

    expect(Hash::check('super-secret-pass', (string) $user->fresh()?->password))->toBeTrue();
});

/**
 * Unverified accounts can sign in to request a fresh activation email.
 */
it('unverified user can log in', function () {
    User::factory()->unverified()->createOne([
        'email' => 'unverified@example.com',
        'password' => 'super-secret-pass',
        'is_banned' => false,
    ]);

    $response = post(route('login'), [
        'email' => 'unverified@example.com',
        'password' => 'super-secret-pass',
    ]);

    assertAuthenticated();
    $response->assertSessionHasNoErrors();
    $response->assertRedirect(config('fortify.home'));
});

/** The success branch still runs for an unactivated account. */
it('unverified login records last login metadata', function () {
    $user = User::factory()->unverified()->createOne([
        'email' => 'unverified-meta@example.com',
        'password' => 'super-secret-pass',
    ]);

    expect($user->last_login_at)->toBeNull();

    post(route('login'), [
        'email' => 'unverified-meta@example.com',
        'password' => 'super-secret-pass',
    ]);

    expect($user->fresh()?->last_login_at)->not->toBeNull();
});

it('account locks after five failed attempts', function () {
    User::factory()->createOne([
        'email' => 'lockme@example.com',
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
    ]);

    for ($i = 0; $i < 5; $i++) {
        post(route('login'), [
            'email' => 'lockme@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = post(route('login'), [
        'email' => 'lockme@example.com',
        'password' => 'super-secret-pass',
    ]);

    assertGuest();
    $response->assertSessionHasErrors('email');
    $response->assertSessionHasErrors([
        'email' => 'Muitas tentativas incorretas. Tente novamente em 30 minuto(s).',
    ]);
});

it('registration creates user when enabled', function () {
    config(['orbita.register' => true]);

    post(route('register'), [
        'username' => 'newcomer',
        'email' => 'newcomer@example.com',
        'password' => 'super-secret-pass',
        'password_confirmation' => 'super-secret-pass',
    ]);

    assertDatabaseHas('users', [
        'username' => 'newcomer',
        'email' => 'newcomer@example.com',
    ]);

    $user = User::where('email', 'newcomer@example.com')->first();
    expect($user)->not->toBeNull()
        // display_name defaults to the username.
        ->and($user?->display_name)->toBe('newcomer')
        // Email verification is required, so the account is not verified yet.
        ->and($user?->email_verified_at)->toBeNull();

    // Fortify signs the new user in straight away (pending-activation state).
    assertAuthenticated();
});

it('registration is rejected when disabled', function () {
    config(['orbita.register' => false]);

    $response = post(route('register'), [
        'username' => 'blocked',
        'email' => 'blocked@example.com',
        'password' => 'super-secret-pass',
        'password_confirmation' => 'super-secret-pass',
    ]);

    assertDatabaseMissing('users', ['email' => 'blocked@example.com']);
    assertGuest();
    $response->assertSessionHasErrors('email');
});
