<?php

namespace App\Enums;

/**
 * Supported OAuth providers.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Linkedin = 'linkedin';
    case Github = 'github';
    case Instagram = 'instagram';

    /**
     * Actual Socialite driver name for the provider.
     */
    public function driver(): string
    {
        return match ($this) {
            self::Linkedin => 'linkedin-openid',
            default => $this->value,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Linkedin => 'LinkedIn',
            self::Github => 'GitHub',
            self::Instagram => 'Instagram',
        };
    }

    public function providesEmail(): bool
    {
        return $this !== self::Instagram;
    }

    public function isEnabled(): bool
    {
        return (bool) config('orbita.social.'.$this->value.'.enabled', false);
    }

    public function isConfigured(): bool
    {
        $service = config('services.'.$this->driver());

        return filled($service['client_id'] ?? null) && filled($service['client_secret'] ?? null);
    }

    public function requiresEmailConfirmation(): bool
    {
        return (bool) config('orbita.social.'.$this->value.'.require_email_confirmation', true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function available(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $provider): bool => $provider->isEnabled() && $provider->isConfigured(),
        ));
    }
}
