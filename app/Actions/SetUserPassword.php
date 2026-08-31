<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Sets an initial password on an account that has none.
 */
final class SetUserPassword
{
    use PasswordValidationRules;

    /**
     * Sets the user's first password, throwing if one already exists.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function set(User $user, array $input): void
    {
        if ($user->hasPassword()) {
            throw ValidationException::withMessages([
                'password' => ['Esta conta já tem uma senha. Use a alteração de senha.'],
            ])->errorBag('updatePassword');
        }

        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validateWithBag('updatePassword');

        $user->forceFill([
            'password' => Hash::make($input['password']),
        ])->save();
    }
}
