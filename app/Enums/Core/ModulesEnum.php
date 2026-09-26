<?php

namespace App\Enums\Core;

use App\Traits\UsefulEnumTrait;

enum ModulesEnum: int
{
    use UsefulEnumTrait;

    case DMS = 700000;
    case HRM = 1000000;
    case Settings = 9800000;
    case MyAccount = 9900000;

    public function description(): string
    {
        return match ($this) {
            self::DMS => 'Document Management',
            self::HRM => 'User Management',
            self::Settings => 'Settings',
            self::MyAccount => 'My Account',
        };
    }
}
