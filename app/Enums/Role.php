<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    /**
     * Roles that can be granted through an invitation. A tenant has exactly
     * one owner, created together with the tenant.
     *
     * @return list<self>
     */
    public static function invitable(): array
    {
        return [self::Admin, self::Staff];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function invitableOptions(): array
    {
        return array_map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()], self::invitable());
    }
}
