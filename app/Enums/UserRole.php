<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'super_admin';

    public function label(): string
    {
        return 'Admin';
    }
}
