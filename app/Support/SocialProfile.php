<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SocialProvider;

/**
 * Normalized social provider profile data.
 */
final readonly class SocialProfile
{
    public function __construct(
        public SocialProvider $provider,
        public string $providerUserId,
        public ?string $email,
        public bool $emailVerified,
        public ?string $nickname,
        public ?string $name,
        public ?string $avatarUrl,
    ) {}

    /** Serialised for the session payload and for user_tokens.payload. */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'provider_user_id' => $this->providerUserId,
            'email' => $this->email,
            'email_verified' => $this->emailVerified,
            'nickname' => $this->nickname,
            'name' => $this->name,
            'avatar_url' => $this->avatarUrl,
        ];
    }

    /**
     * Rebuilds a profile from a stored payload, or null when unusable.
     */
    public static function fromArray(array $payload): ?self
    {
        $provider = SocialProvider::tryFrom((string) ($payload['provider'] ?? ''));
        $providerUserId = (string) ($payload['provider_user_id'] ?? '');

        if ($provider === null || $providerUserId === '') {
            return null;
        }

        return new self(
            provider: $provider,
            providerUserId: $providerUserId,
            email: $payload['email'] ?? null,
            emailVerified: (bool) ($payload['email_verified'] ?? false),
            nickname: $payload['nickname'] ?? null,
            name: $payload['name'] ?? null,
            avatarUrl: $payload['avatar_url'] ?? null,
        );
    }

    /** The best available basis for a generated username: provider handle, then display name. */
    public function usernameSeed(): string
    {
        return (string) ($this->nickname ?: $this->name ?: '');
    }
}
