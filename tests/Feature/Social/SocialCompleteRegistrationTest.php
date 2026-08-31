<?php

declare(strict_types=1);

/**
 * The provider-gave-no-email path to completing social registration.
 */

namespace Tests\Feature\Social;

use App\Enums\SocialProvider;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

it('provider without email redirects to the form', function () {
    FakeSocialite::enableProvider(SocialProvider::Instagram);
    FakeSocialite::fakeUser(SocialProvider::Instagram, email: null, emailVerified: false, name: 'fulano_insta');

    FakeSocialite::hitCallback(SocialProvider::Instagram)->assertRedirect(route('social.complete'));

    assertGuest();
    assertDatabaseCount('users', 0);
});

it('form without a pending payload sends you back to login', function () {
    get(route('social.complete'))->assertRedirect(route('login'));
});

it('expired payload is discarded', function () {
    withSession([SocialAuthController::PENDING_SESSION_KEY => [
        'provider' => 'instagram',
        'provider_user_id' => 'insta-1',
        'expires_at' => now()->subMinute()->getTimestamp(),
    ]])->get(route('social.complete'))->assertRedirect(route('login'));
});

it('submitting a fresh email creates a pending account and logs in', function () {
    Notification::fake();
    FakeSocialite::enableProvider(SocialProvider::Instagram, requireEmailConfirmation: false);
    FakeSocialite::fakeUser(SocialProvider::Instagram, id: 'insta-1', email: null, emailVerified: false, name: 'fulano_insta');
    FakeSocialite::hitCallback(SocialProvider::Instagram);

    post(route('social.complete.store'), ['email' => 'nova@example.com'])
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'nova@example.com')->firstOrFail();
    // Pending even though the provider's toggle is off: a typed address proves nothing.
    expect($user->email_verified_at)->toBeNull();

    assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
    assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'instagram']);
});

it('typing someone elses email neither links nor authenticates', function () {
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Instagram);
    $victim = User::factory()->createOne(['email' => 'vitima@example.com', 'email_verified_at' => now()]);

    FakeSocialite::fakeUser(SocialProvider::Instagram, id: 'attacker', email: null, emailVerified: false);
    FakeSocialite::hitCallback(SocialProvider::Instagram);

    post(route('social.complete.store'), ['email' => 'vitima@example.com'])
        ->assertRedirect(route('login'));

    assertGuest();
    assertDatabaseCount('social_accounts', 0);
    // The victim is merely told someone tried; they decide by clicking or ignoring.
    assertDatabaseHas('user_tokens', ['user_id' => $victim->id, 'token_type' => 'social_link']);
});

it('the pending payload is single use', function () {
    FakeSocialite::enableProvider(SocialProvider::Instagram);
    FakeSocialite::fakeUser(SocialProvider::Instagram, id: 'insta-1', email: null, emailVerified: false);
    FakeSocialite::hitCallback(SocialProvider::Instagram);

    post(route('social.complete.store'), ['email' => 'primeira@example.com']);
    post(route('social.complete.store'), ['email' => 'segunda@example.com'])
        ->assertRedirect(route('login'));

    assertDatabaseMissing('users', ['email' => 'segunda@example.com']);
});

it('username outside the allowed shape is rejected', function () {
    FakeSocialite::enableProvider(SocialProvider::Instagram);
    FakeSocialite::fakeUser(SocialProvider::Instagram, id: 'insta-1', email: null, emailVerified: false);
    FakeSocialite::hitCallback(SocialProvider::Instagram);

    post(route('social.complete.store'), [
        'email' => 'nova@example.com',
        'username' => 'não-vale',
    ])->assertSessionHasErrors('username');

    assertDatabaseCount('users', 0);
});

it('registration disabled is re checked on submit', function () {
    // The admin may close registration while the form sits open.
    FakeSocialite::enableProvider(SocialProvider::Instagram);
    FakeSocialite::fakeUser(SocialProvider::Instagram, id: 'insta-1', email: null, emailVerified: false);
    FakeSocialite::hitCallback(SocialProvider::Instagram);

    config(['orbita.register' => false]);

    post(route('social.complete.store'), ['email' => 'nova@example.com'])
        ->assertSessionHas('error', 'O registro de novas contas está desativado.');

    assertDatabaseCount('users', 0);
});
