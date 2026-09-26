<?php

namespace App\Enums\DMS;

use App\Traits\UsefulEnumTrait;

/**
 * Determines what must happen before a DocumentValidation is considered complete.
 *
 * - Validation          (va): approve + sign in one step (original behaviour).
 * - ApprovalOnly        (ap): document is routed through the approval workflow only;
 *                             no signature is collected even on the final stage.
 * - ApprovalThenSign    (av): document must first complete the approval workflow, then
 *                             each approver added in Document Validation must sign it.
 */
enum DocumentValidationModeEnum: string
{
    use UsefulEnumTrait;

    /** Approve & sign – the original single-step or workflow-with-final-signature path. */
    case Validation = 'va';

    /** Approval only – the document travels through the approval workflow; no signature required. */
    case ApprovalOnly = 'ap';

    /** Approval then sign – first approved via workflow, then signed by validation approvers. */
    case ApprovalThenSign = 'av';

    public function label(): string
    {
        return match ($this) {
            self::Validation       => 'Submit for Validation',
            self::ApprovalOnly     => 'Submit For Approval',
            self::ApprovalThenSign => 'Submit For Approval and Validation',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Validation       => 'Requires approval and a signature from the assigned approvers.',
            self::ApprovalOnly     => 'Routes through the approval workflow only. No signature required.',
            self::ApprovalThenSign => 'First approved through the workflow, then signed by the added approvers.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Validation       => 'fas fa-file-signature',
            self::ApprovalOnly     => 'fas fa-check-circle',
            self::ApprovalThenSign => 'fas fa-tasks',
        };
    }

    /** Returns true when a signature must be collected at the final workflow stage. */
    public function requiresSignature(): bool
    {
        return $this !== self::ApprovalOnly;
    }
}
