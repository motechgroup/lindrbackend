<?php

namespace App\Enums;

enum UserRole: string
{
    case Male = 'male';
    case Female = 'female';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::Admin => 'Admin',
        };
    }
}
