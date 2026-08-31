<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Concerns;

use App\Models\User;

/**
 * Staff accounts for the panel tests.
 *
 * A class rather than a namespaced function so the twelve Admin files can share
 * it without depending on the order Pest happens to include them in.
 */
final class AdminUsers
{
    public static function staff(string $role, string $username): User
    {
        return User::factory()->createOne([
            'username' => $username,
            'role' => $role,
            'is_banned' => false,
            'email_verified_at' => now(),
        ]);
    }

    public static function admin(string $username): User
    {
        return self::staff('admin', $username);
    }

    public static function moderator(string $username): User
    {
        return self::staff('moderator', $username);
    }
}
