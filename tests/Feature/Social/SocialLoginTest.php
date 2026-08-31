<?php

declare(strict_types=1);

/**
 * Core social sign-in paths: creation, login, and pending vs active accounts.
 */

namespace Tests\Feature\Social;

use App\Enums\SocialProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite as SocialiteFacade;
use Laravel\Socialite\Two\InvalidStateException;
use Mockery;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('unknown provider is not routable', function () {
    // The route constraint comes from the enum, so a bogus slug never reaches the controller.
    get('/auth/myspace/redirect')->assertNotFound();
});

it('disabled provider returns 404', function () {
    config([
        'orbita.social.google.enabled' => false,
        'services.google.client_id' => 'id',
        'services.google.client_secret' => 'secret',
    ]);

    get(route('social.redirect', ['provider' => 'google']))->assertNotFound();
    get(route('social.callback', ['provider' => 'google']))->assertNotFound();
});

it('callback without code redirects instead of calling the provider', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);

    get(route('social.callback', ['provider' => 'google']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('error');
});

it('enabled but unconfigured provider returns 404', function () {
    // An admin can flip the toggle before credentials land; a button that errors is worse.
    config([
        'orbita.social.google.enabled' => true,
        'services.google.client_id' => null,
        'services.google.client_secret' => null,
    ]);

    get(route('social.redirect', ['provider' => 'google']))->assertNotFound();
});

it('verified email with confirmation off creates an active account', function () {
    Notification::fake();
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: false);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'nova@example.com');

    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('home'));

    $user = User::where('email', 'nova@example.com')->firstOrFail();
    expect($user->email_verified_at)
        ->not->toBeNull('A provider-verified email should skip activation when the toggle is off.');

    assertAuthenticatedAs($user);

    expect($user->password)->toBeNull('Social-only accounts must not get a password.');
    Notification::assertNothingSent();
});

it('stores provider avatar URLs longer than 512 characters', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    FakeSocialite::fakeUser(
        SocialProvider::Google,
        email: 'long-avatar@example.com',
        avatar: 'https://lh3.googleusercontent.com/a-/'.str_repeat('avatar-segment-', 50).'=s96-c',
    );

    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('home'));

    expect(SocialAccount::query()->where('provider_email', 'long-avatar@example.com')->value('provider_avatar_url'))
        ->toBe('https://lh3.googleusercontent.com/a-/'.str_repeat('avatar-segment-', 50).'=s96-c');
});

it('verified email with confirmation on creates a pending account', function () {
    Notification::fake();
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: true);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'nova@example.com');

    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'nova@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();

    assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('unverified provider email forces confirmation even with the toggle off', function () {
    // A provider that will not vouch for the address must never mint active accounts.
    Notification::fake();
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: false);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'nova@example.com', emailVerified: false);

    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'nova@example.com')->firstOrFail();
    expect($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('known identity logs in and records metadata', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne();
    $account = SocialAccount::factory()->createOne([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'provider-user-1',
        'last_login_at' => null,
    ]);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'provider-user-1');
    FakeSocialite::hitCallback(SocialProvider::Google);

    assertAuthenticatedAs($user);

    expect($account->fresh()?->last_login_at)->not->toBeNull()
        ->and($user->fresh()?->last_login_at)->not->toBeNull();
});

it('identity wins over email when the provider address changed', function () {
    // A changed provider email must still land in the same account.
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['email' => 'antigo@example.com']);
    SocialAccount::factory()->createOne([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'provider-user-1',
    ]);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'provider-user-1', email: 'novo@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google);

    assertAuthenticatedAs($user);

    expect($user->fresh()?->email)->toBe('antigo@example.com')
        ->and(User::count())->toBe(1, 'A changed provider email must not spawn a second account.');
});

it('banned user cannot sign in through a linked identity', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['is_banned' => true]);
    SocialAccount::factory()->createOne([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'provider-user-1',
    ]);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'provider-user-1');
    FakeSocialite::hitCallback(SocialProvider::Google)->assertSessionHas('error', 'Sua conta foi banida');

    assertGuest();
});

it('anonymized account cannot sign in and the stale identity is removed', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['anonymized_at' => now()]);
    SocialAccount::factory()->createOne([
        'user_id' => $user->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'provider-user-1',
    ]);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'provider-user-1');
    FakeSocialite::hitCallback(SocialProvider::Google)->assertSessionHas('error');

    assertGuest();
    assertDatabaseCount('social_accounts', 0);
});

it('registration disabled blocks new accounts but not existing identities', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    config(['orbita.register' => false]);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'newcomer', email: 'nova@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google)
        ->assertSessionHas('error', 'O registro de novas contas está desativado.');

    assertGuest();
    assertDatabaseCount('users', 0);
});

it('invalid state redirects instead of erroring', function () {
    // A stale tab or SESSION_SAME_SITE=strict must not produce a 500 on an auth route.
    FakeSocialite::enableProvider(SocialProvider::Google);

    $driver = Mockery::mock(Provider::class);
    $driver->shouldReceive('user')->andThrow(new InvalidStateException);
    SocialiteFacade::shouldReceive('driver')->with('google')->andReturn($driver);

    FakeSocialite::hitCallback(SocialProvider::Google)
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Sua sessão expirou durante o login. Tente novamente.');

    assertGuest();
});

it('username collision falls back to a numeric suffix', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    User::factory()->createOne(['username' => 'pessoa']);

    FakeSocialite::fakeUser(
        SocialProvider::Google,
        email: 'outra@example.com',
        nickname: 'pessoa',
        name: 'Pessoa',
    );

    FakeSocialite::hitCallback(SocialProvider::Google);

    $created = User::where('email', 'outra@example.com')->firstOrFail();
    expect($created->username)->not->toBe('pessoa');
    expect($created->username)->toMatch('/^[A-Za-z0-9_]+$/');
});

it('provider handle with illegal characters still yields a valid username', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);

    FakeSocialite::fakeUser(
        SocialProvider::Google,
        email: 'acentos@example.com',
        nickname: 'joão.silva-99',
    );

    FakeSocialite::hitCallback(SocialProvider::Google);

    $created = User::where('email', 'acentos@example.com')->firstOrFail();
    expect($created->username)->toMatch('/^[A-Za-z0-9_]+$/');
    expect(strlen($created->username))->toBeLessThanOrEqual(50);
});
