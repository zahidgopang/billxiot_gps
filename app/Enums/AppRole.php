<?php

namespace App\Enums;

enum AppRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Client = 'client';
    case EndUser = 'user';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
            self::Client => 'Client',
            self::EndUser => 'End User',
        };
    }

    public function panel(): string
    {
        return match ($this) {
            self::SuperAdmin, self::Admin => 'admin',
            self::Client => 'client',
            self::EndUser => 'user',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles that may access a management panel (not end-user tracker UI).
     *
     * @return list<string>
     */
    public static function panelRoles(): array
    {
        return [
            self::SuperAdmin->value,
            self::Admin->value,
            self::Client->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function creatableBy(AppRole $actor): array
    {
        return match ($actor) {
            self::SuperAdmin => self::values(),
            self::Admin => [
                self::Admin->value,
                self::Client->value,
                self::EndUser->value,
            ],
            self::Client => [
                self::EndUser->value,
            ],
            self::EndUser => [],
        };
    }
}
