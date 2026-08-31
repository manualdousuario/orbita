<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SocialProvider;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Maps a Socialite user to a normalized SocialProfile.
 */
final class SocialProfileNormalizer
{
    public static function fromSocialite(SocialProvider $provider, SocialiteUser $user): SocialProfile
    {
        $raw = $user->getRaw();
        $email = $user->getEmail() ?: null;

        $emailVerified = match ($provider) {
            SocialProvider::Google => filter_var(
                $raw['email_verified'] ?? $raw['verified_email'] ?? false,
                FILTER_VALIDATE_BOOL,
            ),

            SocialProvider::Linkedin => filter_var(
                $raw['email_verified'] ?? $user->email_verified ?? false,
                FILTER_VALIDATE_BOOL,
            ),

            SocialProvider::Github => filled($email),

            SocialProvider::Instagram => false,
        };

        $nickname = $user->getNickname() ?: null;
        $name = $user->getName() ?: null;

        return new SocialProfile(
            provider: $provider,
            providerUserId: (string) $user->getId(),
            email: $email === null ? null : Str::lower(trim($email)),
            emailVerified: $emailVerified,
            nickname: $nickname,
            name: $name,
            avatarUrl: $user->getAvatar() ?: null,
        );
    }
}
