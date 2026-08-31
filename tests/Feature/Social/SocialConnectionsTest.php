<?php

declare(strict_types=1);

/**
 * Connected social accounts under Configurações > Contas conectadas.
 */

namespace Tests\Feature\Social;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

it('an authenticated user connecting links without email confirmation', function () {
    // Holding the session AND the provider account proves more than the email loop would.
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: true);
    $user = User::factory()->createOne();

    actingAs($user);
    FakeSocialite::fakeUser(SocialProvider::Google, id: 'g-1', email: 'outro@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google)
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google']);
    assertDatabaseMissing('user_tokens', ['token_type' => 'social_link']);
});

it('connecting never rewrites the account email', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['email' => 'meu@example.com']);

    actingAs($user);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'google@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google);

    expect($user->fresh()?->email)->toBe('meu@example.com');
});

it('an identity already owned by someone else is refused', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $owner = User::factory()->createOne();
    SocialAccount::factory()->createOne([
        'user_id' => $owner->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'shared-identity',
    ]);

    $intruder = User::factory()->createOne();
    actingAs($intruder);
    FakeSocialite::fakeUser(SocialProvider::Google, id: 'shared-identity');
    FakeSocialite::hitCallback(SocialProvider::Google)->assertSessionHas('error');

    assertDatabaseMissing('social_accounts', ['user_id' => $intruder->id]);
    assertDatabaseHas('social_accounts', ['user_id' => $owner->id, 'provider_user_id' => 'shared-identity']);
});

it('disconnecting removes the identity', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne();
    SocialAccount::factory()->createOne(['user_id' => $user->id, 'provider' => SocialProvider::Google]);

    actingAs($user)
        ->delete(route('users.connections.destroy', ['username' => $user->username, 'provider' => 'google']))
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    assertDatabaseCount('social_accounts', 0);
});

it('the last access method cannot be disconnected', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['password' => null]);
    SocialAccount::factory()->createOne(['user_id' => $user->id, 'provider' => SocialProvider::Google]);

    actingAs($user)
        ->delete(route('users.connections.destroy', ['username' => $user->username, 'provider' => 'google']))
        ->assertSessionHas('error');

    assertDatabaseCount('social_accounts', 1);
});

it('with a password set the last provider can be disconnected', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['password' => 'uma-senha-bem-longa']);
    SocialAccount::factory()->createOne(['user_id' => $user->id, 'provider' => SocialProvider::Google]);

    actingAs($user)
        ->delete(route('users.connections.destroy', ['username' => $user->username, 'provider' => 'google']));

    assertDatabaseCount('social_accounts', 0);
});

it('nobody may manage another users connections', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $victim = User::factory()->createOne();
    SocialAccount::factory()->createOne(['user_id' => $victim->id, 'provider' => SocialProvider::Google]);

    actingAs(User::factory()->createOne())
        ->delete(route('users.connections.destroy', ['username' => $victim->username, 'provider' => 'google']))
        ->assertForbidden();

    assertDatabaseCount('social_accounts', 1);
});

it('not even an admin may manage another users connections', function () {
    // Narrower than UserPolicy::update: an admin linking to another's account is a backdoor.
    FakeSocialite::enableProvider(SocialProvider::Google);
    $victim = User::factory()->createOne();
    $admin = User::factory()->createOne(['role' => 'admin']);

    actingAs($admin)
        ->post(route('users.connections.store', ['username' => $victim->username, 'provider' => 'google']))
        ->assertForbidden();
});
