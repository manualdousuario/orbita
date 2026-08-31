<?php

namespace App\Enums;

/**
 * The three fixed account roles (users.role).
 */
enum UserRole: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function isStaff(): bool
    {
        return $this === self::Admin || $this === self::Moderator;
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'Usuário',
            self::Moderator => 'Moderador',
            self::Admin => 'Administrador',
        };
    }

    /**
     * value => label map for Filament Select/SelectFilter options.
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $carry, self $role) => $carry + [$role->value => $role->label()],
            [],
        );
    }
}
