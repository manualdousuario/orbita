<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

/**
 * Resets a user's password.
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Resets the user's password, rejecting banned accounts.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if ($user->is_banned) {
            throw ValidationException::withMessages([
                'email' => ['Sua conta foi banida'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($input['password']),
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();
    }
}
