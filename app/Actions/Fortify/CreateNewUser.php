<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\TurnstileService;
use App\Support\UsernameGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registers a new user account.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validates registration input and creates the user.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        if (! config('orbita.register', true)) {
            throw ValidationException::withMessages([
                'email' => ['O registro de novas contas está desativado.'],
            ]);
        }

        $this->validateTurnstile($input);

        if (blank($input['username'] ?? null)) {
            $input['username'] = UsernameGenerator::fromEmail((string) ($input['email'] ?? ''));
        }

        Validator::make($input, [
            'username' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9_]+$/',
                Rule::unique(User::class, 'username'),
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'display_name' => ['nullable', 'string', 'max:100'],
            'password' => $this->passwordRules(),
        ], [
            'username.regex' => 'O nome de usuário deve conter apenas letras, números e underscore.',
        ])->validate();

        // Email verification is required, so email_verified_at stays null.
        return User::create([
            'username' => $input['username'],
            'email' => $input['email'],
            'display_name' => $input['display_name'] ?? $input['username'],
            'password' => Hash::make($input['password']),
        ]);
    }

    /**
     * Validates the Turnstile token when enabled.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    private function validateTurnstile(array $input): void
    {
        $turnstile = app(TurnstileService::class);

        if (! $turnstile->isEnabled()) {
            return;
        }

        $token = (string) ($input['cf-turnstile-response'] ?? '');

        if (! $turnstile->validate($token, (string) request()->ip())) {
            throw ValidationException::withMessages([
                'email' => ['Verificação de segurança inválida. Por favor, tente novamente.'],
            ]);
        }
    }
}
