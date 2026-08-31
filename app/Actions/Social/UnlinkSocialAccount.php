<?php

declare(strict_types=1);

namespace App\Actions\Social;

use App\Enums\SocialProvider;
use App\Models\User;

/**
 * Disconnects a social account from a user.
 */
final class UnlinkSocialAccount
{
    /** @return array{0: bool, 1: string} [succeeded, pt-BR message] */
    public function handle(User $user, SocialProvider $provider): array
    {
        $account = $user->socialAccounts()->where('provider', $provider->value)->first();

        if ($account === null) {
            return [false, 'Esta conta do '.$provider->label().' não está conectada.'];
        }

        if (! $user->hasPassword() && $user->socialAccounts()->count() <= 1) {
            return [false, 'Esta é sua única forma de entrar no '.config('orbita.name', 'Órbita').'. Defina uma senha antes de desconectar o '.$provider->label().'.'];
        }

        $account->delete();

        return [true, 'Conta do '.$provider->label().' desconectada.'];
    }
}
