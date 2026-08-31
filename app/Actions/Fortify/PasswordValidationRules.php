<?php

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Minimum length is driven by config('orbita.password.min_length').
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        $minLength = max(8, (int) config('orbita.password.min_length', 10));

        return ['required', 'string', Password::min($minLength), 'confirmed'];
    }
}
