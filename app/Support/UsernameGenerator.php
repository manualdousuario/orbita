<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Generates unique usernames from emails, nicknames, or a base.
 */
final class UsernameGenerator
{
    public static function fromEmail(string $email): string
    {
        return self::unique(self::sanitize(Str::before($email, '@')));
    }

    /** For providers that give a handle or display name but no email (Instagram). */
    public static function fromNickname(string $nickname): string
    {
        return self::unique(self::sanitize($nickname));
    }

    /** Appends the smallest numeric suffix that clears the unique index, staying within 50 chars. */
    public static function unique(string $base): string
    {
        $base = $base !== '' ? $base : 'user';

        $username = $base;
        $suffix = 1;
        while (User::where('username', $username)->exists()) {
            $username = Str::substr($base, 0, 50 - strlen((string) $suffix)).$suffix;
            $suffix++;
        }

        return $username;
    }

    /**
     * Slugifies with '_' and caps at 42 so the numeric suffix always fits.
     */
    private static function sanitize(string $value): string
    {
        $base = preg_replace('/[^A-Za-z0-9_]/', '', Slug::make($value, '_'));

        return $base !== '' ? Str::substr($base, 0, 42) : '';
    }
}
