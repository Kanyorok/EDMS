<?php

namespace App\Traits\Model;

use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;

/**
 * Lightweight maker-checker gate shared by the Medical Scheme tables (funds, packages,
 * beneficiaries, dependants, contributions, disbursements) — mirrors the existing
 * ClaimStatus / MedicalContributorStatus CodeDetail-FK convention used elsewhere in
 * the Insurance module, rather than the heavier generic Workflow engine.
 */
trait HasMedicalFundApproval
{
    public function approvalStatus()
    {
        return $this->belongsTo(CodeDetail::class, 'ApprovalStatus', 'ID');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'ApprovedBy', 'Id');
    }

    public function getApprovalStatusCodeAttribute(): ?string
    {
        return $this->approvalStatus?->Value;
    }

    public function isPendingApproval(): bool
    {
        return $this->approval_status_code === 'P' || $this->approval_status_code === null;
    }

    public function isApproved(): bool
    {
        return $this->approval_status_code === 'A';
    }

    public function isRejected(): bool
    {
        return $this->approval_status_code === 'R';
    }

    public static function pendingApprovalStatusId(): ?int
    {
        return CodeDetail::where('CodeID', 'MedicalFundApprovalStatus')->where('Value', 'P')->value('ID');
    }

    public static function approvedStatusId(): ?int
    {
        return CodeDetail::where('CodeID', 'MedicalFundApprovalStatus')->where('Value', 'A')->value('ID');
    }

    public static function rejectedStatusId(): ?int
    {
        return CodeDetail::where('CodeID', 'MedicalFundApprovalStatus')->where('Value', 'R')->value('ID');
    }

    public function markApproved(User $user): void
    {
        $this->update([
            'ApprovalStatus' => static::approvedStatusId(),
            'ApprovedBy' => $user->Id,
            'ApprovedOn' => now(),
            'RejectionReason' => null,
        ]);
    }

    public function markRejected(User $user, ?string $reason = null): void
    {
        $this->update([
            'ApprovalStatus' => static::rejectedStatusId(),
            'ApprovedBy' => $user->Id,
            'ApprovedOn' => now(),
            'RejectionReason' => $reason,
        ]);
    }
}
