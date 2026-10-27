<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\LinkGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedEmailDomain implements ValidationRule
{
    public const MESSAGE = 'Este provedor de e-mail não é aceito.';

    public static function isBlocked(string $email): bool
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $host = strtolower(trim(substr($email, $at + 1)));

        foreach (LinkGuard::domains('email_domain_blocklist') as $domain) {
            if (LinkGuard::matches($host, $domain)) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && self::isBlocked($value)) {
            $fail(self::MESSAGE);
        }
    }
}
