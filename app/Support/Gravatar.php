<?php

namespace App\Support;

/**
 * Builds Gravatar URLs for server-side lookup only.
 */
class Gravatar
{
    private const BASE = 'https://www.gravatar.com/avatar/';

    /** Current recommended Gravatar identifier: sha256 of the trimmed, lowercased email. */
    public static function hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /**
     * Gravatar URL using d=404 so a missing avatar returns a plain 404.
     */
    public static function url(string $email, int $size = 300): string
    {
        return self::BASE.self::hash($email).'?s='.$size.'&d=404';
    }
}
