<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\SocialProvider;
use App\Models\User;
use App\Rules\AllowedEmailDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Social\Concerns\FakeSocialite;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'orbita.register' => true,
        'orbita.moderation.email_domain_blocklist' => "# spam\nbackyr.com\n*.atomicmail.io",
    ]);
});

it('matches the domain and its subdomains, case insensitive', function () {
    expect(AllowedEmailDomain::isBlocked('a@backyr.com'))->toBeTrue()
        ->and(AllowedEmailDomain::isBlocked('a@mx.BACKYR.com'))->toBeTrue()
        ->and(AllowedEmailDomain::isBlocked('a@atomicmail.io'))->toBeTrue()
        ->and(AllowedEmailDomain::isBlocked('a@notbackyr.com'))->toBeFalse()
        ->and(AllowedEmailDomain::isBlocked('a@gmail.com'))->toBeFalse();
});

it('registration with a blocked domain is refused', function () {
    post(route('register'), [
        'username' => 'robo',
        'email' => 'robo@backyr.com',
        'password' => 'super-secret-pass',
        'password_confirmation' => 'super-secret-pass',
    ])->assertSessionHasErrors(['email' => AllowedEmailDomain::MESSAGE]);

    assertDatabaseMissing('users', ['email' => 'robo@backyr.com']);
});

it('registration with an allowed domain still works', function () {
    post(route('register'), [
        'username' => 'pessoa',
        'email' => 'pessoa@example.com',
        'password' => 'super-secret-pass',
        'password_confirmation' => 'super-secret-pass',
    ])->assertSessionHasNoErrors();

    expect(User::where('email', 'pessoa@example.com')->exists())->toBeTrue();
});

it('changing the email to a blocked domain is refused', function () {
    $user = User::factory()->createOne([
        'username' => 'maria',
        'email' => 'maria@example.com',
        'email_verified_at' => now(),
    ]);

    actingAs($user)
        ->put(route('users.email', ['username' => 'maria']), [
            'new_email' => 'maria@backyr.com',
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['new_email' => AllowedEmailDomain::MESSAGE]);
});

it('social signup with a blocked domain creates no account', function () {
    Notification::fake();
    FakeSocialite::enableProvider(SocialProvider::Google, requireEmailConfirmation: false);
    FakeSocialite::fakeUser(SocialProvider::Google, email: 'robo@backyr.com');

    FakeSocialite::hitCallback(SocialProvider::Google)->assertRedirect(route('login'));

    assertDatabaseMissing('users', ['email' => 'robo@backyr.com']);
});
