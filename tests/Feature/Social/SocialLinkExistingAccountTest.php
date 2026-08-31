<?php

declare(strict_types=1);

/**
 * Linking when the provider's email already belongs to an account.
 */

namespace Tests\Feature\Social;

use App\Enums\SocialProvider;
use App\Mail\SocialLinkConfirmationMail;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\UserToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/** The token from the confirmation email (the row only stores a digest). */
function tokenFromLinkMail(): string
{
    $plain = null;

    Mail::assertQueued(SocialLinkConfirmationMail::class, function (SocialLinkConfirmationMail $mail) use (&$plain): bool {
        $plain = $mail->token;

        return true;
    });

    return (string) $plain;
}

it('verified provider and verified account link automatically', function () {
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['email' => 'existente@example.com', 'email_verified_at' => now()]);

    FakeSocialite::fakeUser(SocialProvider::Google, email: 'existente@example.com', emailVerified: true);
    FakeSocialite::hitCallback(SocialProvider::Google);

    assertAuthenticatedAs($user);
    assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
    ]);
});

it('unverified local account requires confirmation and does not log in', function () {
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->unverified()->createOne(['email' => 'existente@example.com']);

    FakeSocialite::fakeUser(SocialProvider::Google, email: 'existente@example.com', emailVerified: true);
    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('login'));

    assertGuest();
    assertDatabaseCount('social_accounts', 0);
    assertDatabaseHas('user_tokens', [
        'user_id' => $user->id,
        'token_type' => 'social_link',
        'is_used' => false,
    ]);
});

it('unverified provider email requires confirmation even for a verified account', function () {
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->createOne(['email' => 'existente@example.com', 'email_verified_at' => now()]);

    FakeSocialite::fakeUser(SocialProvider::Google, email: 'existente@example.com', emailVerified: false);
    FakeSocialite::hitCallback(SocialProvider::Google);

    assertGuest();
    assertDatabaseCount('social_accounts', 0);
    assertDatabaseHas('user_tokens', ['user_id' => $user->id, 'token_type' => 'social_link']);
});

it('confirmation email goes to the account address not the provider one', function () {
    // The email is the proof of ownership, so send it to the account address only.
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    User::factory()->unverified()->createOne(['email' => 'dono@example.com']);

    FakeSocialite::fakeUser(SocialProvider::Google, email: 'dono@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google);

    Mail::assertQueued(
        SocialLinkConfirmationMail::class,
        fn (SocialLinkConfirmationMail $mail) => $mail->hasTo('dono@example.com'),
    );
});

it('clicking the link connects verifies and authenticates', function () {
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->unverified()->createOne(['email' => 'dono@example.com']);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'provider-user-9', email: 'dono@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google);

    $token = UserToken::where('token_type', 'social_link')->firstOrFail();
    expect($token->token)->not->toBe(tokenFromLinkMail(), 'the row must not hold the clickable value');

    get(route('social.link.confirm', ['token' => tokenFromLinkMail()]))
        ->assertRedirect(route('users.edit', ['username' => $user->username]));

    assertAuthenticatedAs($user);

    // Clicking a link sent to the account mailbox proves the address.
    expect($user->fresh()?->email_verified_at)->not->toBeNull();
    expect($token->fresh()?->is_used)->toBeTrue();

    assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_user_id' => 'provider-user-9',
    ]);
});

it('used expired and unknown tokens are refused', function () {
    $user = User::factory()->createOne();

    // Stored hashed, requested in the clear — otherwise the lookup would miss.
    UserToken::create([
        'user_id' => $user->id, 'token' => UserToken::hashToken(str_repeat('a', 64)), 'token_type' => 'social_link',
        'email' => $user->email, 'is_used' => true, 'request_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);
    UserToken::create([
        'user_id' => $user->id, 'token' => UserToken::hashToken(str_repeat('b', 64)), 'token_type' => 'social_link',
        'email' => $user->email, 'is_used' => false, 'request_ip' => '127.0.0.1',
        'expires_at' => now()->subDay(),
    ]);

    get(route('social.link.confirm', ['token' => str_repeat('a', 64)]))->assertSessionHas('error');
    get(route('social.link.confirm', ['token' => str_repeat('b', 64)]))->assertSessionHas('error');
    get(route('social.link.confirm', ['token' => str_repeat('c', 64)]))->assertSessionHas('error');

    assertGuest();
    assertDatabaseCount('social_accounts', 0);
});

it('identity claimed by someone else meanwhile is refused', function () {
    // The token may sit in a mailbox for hours; the world can change underneath it.
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    $user = User::factory()->unverified()->createOne(['email' => 'dono@example.com']);

    FakeSocialite::fakeUser(SocialProvider::Google, id: 'contested', email: 'dono@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google);

    SocialAccount::factory()->createOne([
        'user_id' => User::factory()->createOne()->id,
        'provider' => SocialProvider::Google,
        'provider_user_id' => 'contested',
    ]);

    get(route('social.link.confirm', ['token' => tokenFromLinkMail()]))->assertSessionHas('error');

    assertGuest();
    assertDatabaseMissing('social_accounts', ['user_id' => $user->id]);
});

it('banned account is refused before any email is sent', function () {
    Mail::fake();
    FakeSocialite::enableProvider(SocialProvider::Google);
    User::factory()->createOne(['email' => 'banido@example.com', 'is_banned' => true]);

    FakeSocialite::fakeUser(SocialProvider::Google, email: 'banido@example.com');
    FakeSocialite::hitCallback(SocialProvider::Google)->assertSessionHas('error', 'Sua conta foi banida');

    assertGuest();
    Mail::assertNothingQueued();
});
