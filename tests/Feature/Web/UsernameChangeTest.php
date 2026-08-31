<?php

declare(strict_types=1);

/**
 * Username changes with a 30-day cooldown, exempt for admins.
 */

namespace Tests\Feature\Web;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function usernamePayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'novohandle',
        'display_name' => 'Nome Público',
        'bio' => '',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $attrs
 */
function usernameUser(array $attrs = []): User
{
    return User::factory()->createOne(array_merge([
        'username' => 'handle_atual',
        'display_name' => 'Nome Público',
        'email_verified_at' => now(),
    ], $attrs));
}

it('a user can change their username when never changed', function () {
    $user = usernameUser(['username_changed_at' => null]);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'novohandle']))
        ->assertRedirect(route('users.edit', ['username' => 'novohandle']));

    $user->refresh();
    expect($user->username)->toBe('novohandle')
        ->and($user->username_changed_at)->not->toBeNull();
});

it('a user cannot change their username within the cooldown', function () {
    $user = usernameUser(['username_changed_at' => now()->subDays(10)]);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'novohandle']))
        ->assertSessionHasErrors('username');

    expect($user->refresh()->username)->toBe('handle_atual', 'the username must not change during the cooldown');
});

it('the cooldown expires after 30 days', function () {
    $user = usernameUser(['username_changed_at' => now()->subDays(31)]);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'novohandle']))
        ->assertRedirect(route('users.edit', ['username' => 'novohandle']));

    expect($user->refresh()->username)->toBe('novohandle');
});

it('submitting the same username during cooldown is a noop not an error', function () {
    $user = usernameUser(['username' => 'handle_atual', 'username_changed_at' => now()->subDay()]);

    // Display-name edits must still work mid-cooldown.
    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload([
            'username' => 'handle_atual',
            'display_name' => 'Novo Nome',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('users.edit', ['username' => 'handle_atual']));

    expect($user->refresh()->display_name)->toBe('Novo Nome');
});

it('a noop username does not stamp username changed at', function () {
    $user = usernameUser(['username' => 'handle_atual', 'username_changed_at' => null]);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'handle_atual']))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->username_changed_at)
        ->toBeNull('an unchanged username must not start the cooldown');
});

it('an update that omits username leaves it untouched', function () {
    $user = usernameUser(['username' => 'handle_atual', 'username_changed_at' => null]);

    // An avatar-only or display-name-only update need not resend the username.
    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), [
            'display_name' => 'Só o Nome',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->username)->toBe('handle_atual')
        ->and($user->username_changed_at)->toBeNull()
        ->and($user->display_name)->toBe('Só o Nome');
});

it('username must be unique', function () {
    usernameUser(['username' => 'ocupado', 'email' => 'outro@local.test']);
    $user = usernameUser(['username' => 'handle_atual']);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'ocupado']))
        ->assertSessionHasErrors('username');

    expect($user->refresh()->username)->toBe('handle_atual');
});

it('username rejects invalid characters', function () {
    $user = usernameUser(['username_changed_at' => null]);

    actingAs($user)
        ->put(route('users.update', ['username' => 'handle_atual']), usernamePayload(['username' => 'nome com espaço']))
        ->assertSessionHasErrors('username');

    expect($user->refresh()->username)->toBe('handle_atual');
});

it('an admin can change another users username during their cooldown', function () {
    $admin = User::factory()->createOne(['username' => 'chefe', 'role' => 'admin', 'email_verified_at' => now()]);
    $target = usernameUser(['username' => 'alvo', 'username_changed_at' => now()->subDay()]);

    actingAs($admin)
        ->put(route('users.update', ['username' => 'alvo']), usernamePayload(['username' => 'corrigido']))
        ->assertRedirect(route('users.edit', ['username' => 'corrigido']));

    expect($target->refresh()->username)->toBe('corrigido');
});

it('the edit form locks the field during cooldown', function () {
    $user = usernameUser(['username_changed_at' => now()->subDay()]);

    actingAs($user)->get(route('users.edit', ['username' => 'handle_atual']))
        ->assertOk()
        ->assertSee('Você poderá alterar novamente em')
        ->assertSee('readonly', false);
});

it('the edit form is open when the cooldown has passed', function () {
    $user = usernameUser(['username_changed_at' => null]);

    actingAs($user)->get(route('users.edit', ['username' => 'handle_atual']))
        ->assertOk()
        ->assertSee('Pode ser alterado uma vez a cada 30 dias')
        ->assertDontSee('Você poderá alterar novamente em');
});
