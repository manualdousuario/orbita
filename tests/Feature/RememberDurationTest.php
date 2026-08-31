<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SocialProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

const SIX_MONTHS = 259200;
const ONE_MONTH = 43200;

function recallerCookieMinutes(TestResponse $response): ?int
{
    $cookie = $response->getCookie(Auth::guard('web')->getRecallerName(), decrypt: false);

    return $cookie === null
        ? null
        : (int) round(($cookie->getExpiresTime() - time()) / 60);
}

function activeUser(string $email): User
{
    return User::factory()->createOne([
        'email' => $email,
        'password' => 'super-secret-pass',
        'email_verified_at' => now(),
        'is_banned' => false,
    ]);
}

it('issues a one month remember cookie when the box is left alone', function () {
    $user = activeUser('plain@example.com');

    $response = post(route('login'), [
        'email' => 'plain@example.com',
        'password' => 'super-secret-pass',
    ]);

    assertAuthenticatedAs($user);
    expect(recallerCookieMinutes($response))->toEqualWithDelta(ONE_MONTH, 5);
});

it('issues a six month remember cookie when the box is ticked', function () {
    $user = activeUser('sticky@example.com');

    $response = post(route('login'), [
        'email' => 'sticky@example.com',
        'password' => 'super-secret-pass',
        'remember' => '1',
    ]);

    assertAuthenticatedAs($user);
    expect(recallerCookieMinutes($response))->toEqualWithDelta(SIX_MONTHS, 5);
});

it('accepts remember_me as an alias for the box', function () {
    $user = activeUser('alias@example.com');

    $response = post(route('login'), [
        'email' => 'alias@example.com',
        'password' => 'super-secret-pass',
        'remember_me' => '1',
    ]);

    assertAuthenticatedAs($user);
    expect(recallerCookieMinutes($response))->toEqualWithDelta(SIX_MONTHS, 5);
});

it('a failed login queues no remember cookie', function () {
    activeUser('careful@example.com');

    $response = post(route('login'), [
        'email' => 'careful@example.com',
        'password' => 'wrong-password',
    ]);

    expect(recallerCookieMinutes($response))->toBeNull();
});

it('social sign-in remembers for six months', function () {
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: false);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'social@example.com');

    $response = FakeSocialite::hitCallback(SocialProvider::Google);

    expect(recallerCookieMinutes($response))->toEqualWithDelta(SIX_MONTHS, 5);
});

it('following a verification link remembers for six months', function () {
    $user = User::factory()->createOne([
        'email' => 'verify@example.com',
        'email_verified_at' => null,
    ]);

    $link = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes((int) config('auth.verification.expire')),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        absolute: true,
    );

    $response = get($link);

    assertAuthenticatedAs($user);
    expect(recallerCookieMinutes($response))->toEqualWithDelta(SIX_MONTHS, 5);
});

it('the guard carries a one month floor for any path that sets no duration', function () {
    expect(config('auth.guards.web.remember'))->toBe(ONE_MONTH);
});
