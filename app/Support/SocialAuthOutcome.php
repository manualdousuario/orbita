<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SocialAuthStatus;
use App\Models\User;

/**
 * The result of a social auth flow: status plus user or message.
 */
final readonly class SocialAuthOutcome
{
    public function __construct(
        public SocialAuthStatus $status,
        public ?User $user = null,
        public ?string $message = null,
    ) {}

    public static function loggedIn(User $user, ?string $message = null): self
    {
        return new self(SocialAuthStatus::LoggedIn, $user, $message);
    }

    public static function needsVerification(User $user, ?string $message = null): self
    {
        return new self(SocialAuthStatus::NeedsVerification, $user, $message);
    }

    public static function needsEmail(?string $message = null): self
    {
        return new self(SocialAuthStatus::NeedsEmail, null, $message);
    }

    public static function linkPending(string $message): self
    {
        return new self(SocialAuthStatus::LinkPending, null, $message);
    }

    public static function error(string $message): self
    {
        return new self(SocialAuthStatus::Error, null, $message);
    }

    public function failed(): bool
    {
        return $this->status === SocialAuthStatus::Error;
    }
}
