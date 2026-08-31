<?php

namespace App\Policies;

use App\Models\User;

/**
 * User authorization: account management is owner-or-admin, moderators only manage content.
 */
class UserPolicy
{
    public function update(User $user, User $target): bool
    {
        return (int) $user->id === (int) $target->id || $user->isAdmin();
    }

    public function manageConnections(User $user, User $target): bool
    {
        return (int) $user->id === (int) $target->id;
    }
}
