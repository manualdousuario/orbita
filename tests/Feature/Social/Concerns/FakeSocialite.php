<?php

declare(strict_types=1);

namespace Tests\Feature\Social\Concerns;

use App\Enums\SocialProvider;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

use function Pest\Laravel\get;

/**
 * Helpers for driving the OAuth callback without touching the network.
 *
 * Static methods rather than a trait: inside a Pest closure $this resolves to
 * Pest\PendingCalls\TestCall for the analyser, so trait methods reached through
 * $this-> would be unresolvable.
 */
final class FakeSocialite
{
    /** Switches a provider on and gives it credentials, so it passes the controller's gate. */
    public static function enableProvider(SocialProvider $provider, bool $requireEmailConfirmation = false): void
    {
        config([
            'orbita.social.'.$provider->value.'.enabled' => true,
            'orbita.social.'.$provider->value.'.require_email_confirmation' => $requireEmailConfirmation,
            'services.'.$provider->driver().'.client_id' => 'test-client-id',
            'services.'.$provider->driver().'.client_secret' => 'test-client-secret',
            'services.'.$provider->driver().'.redirect' => '/auth/'.$provider->value.'/callback',
        ]);
    }

    /**
     * @param  array<string, mixed>  $raw  extra raw claims. Google's `email_verified` lives ONLY
     *                                     here, never on the mapped object -- see the normalizer.
     */
    public static function fakeUser(
        SocialProvider $provider,
        string $id = 'provider-user-1',
        ?string $email = 'social@example.com',
        bool $emailVerified = true,
        ?string $nickname = null,
        ?string $name = 'Pessoa Social',
        ?string $avatar = null,
        array $raw = [],
    ): SocialiteUser {
        $socialiteUser = new SocialiteUser;

        $socialiteUser->map([
            'id' => $id,
            'nickname' => $nickname,
            'name' => $name,
            'email' => $email,
            'avatar' => $avatar,
            // LinkedIn reads it off the mapped object as well; harmless for the others.
            'email_verified' => $emailVerified,
        ]);

        $socialiteUser->setRaw($raw + [
            'sub' => $id,
            'email' => $email,
            'email_verified' => $emailVerified,
        ]);

        $driver = Mockery::mock(SocialiteProvider::class);
        $driver->shouldReceive('user')->andReturn($socialiteUser);

        Socialite::shouldReceive('driver')
            ->with($provider->driver())
            ->andReturn($driver);

        return $socialiteUser;
    }

    /** Drives the callback as if the provider had just redirected back. */
    public static function hitCallback(SocialProvider $provider): TestResponse
    {
        return get(route('social.callback', ['provider' => $provider->value, 'code' => 'fake-auth-code']));
    }
}
