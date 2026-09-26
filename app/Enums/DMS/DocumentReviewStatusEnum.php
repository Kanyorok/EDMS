<?php

namespace App\Enums\DMS;

use App\Traits\UsefulEnumTrait;

enum DocumentReviewStatusEnum: string
{
    use UsefulEnumTrait;

    case Pending = 'pe';
    case Reviewed = 'rv';

    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reviewed => 'Reviewed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning',
            self::Reviewed => 'bg-success',
        };
    }
}
