<?php

namespace App\Enums\DMS;

use App\Traits\UsefulEnumTrait;

enum DocumentValidationStatusEnum: string
{
    use UsefulEnumTrait;

    case Pending = 'pe';
    case Submitted = 'su';
    case Approved = 'ap';
    case Rejected = 're';

    /**
     * The t_CodeDetails Description this status is stored under.
     *
     * These strings are matched verbatim by the workflow stored procedures and by
     * ApprovalWorkflowService::advanceToNextStage(), so they must not be reworded.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Submitted => 'Submitted for Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning',
            self::Submitted => 'bg-info',
            self::Approved => 'bg--success',
            self::Rejected => 'bg-danger',
        };
    }
}
