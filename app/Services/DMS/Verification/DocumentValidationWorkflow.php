<?php

namespace App\Services\DMS\Verification;

use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Models\Auth\User;
use App\Models\Core\Approval\WorkflowStage;
use App\Models\DMS\DocumentValidation;
use App\Services\Workflow\ApprovalWorkflow;
use Illuminate\Support\Collection;

/**
 * Drives document validations through the approval workflow bound to their validation type.
 *
 * The generic engine binds one workflow per source *table*
 * (ApprovalWorkflowService::getPermissionFromStage does Workflow::where('Source', $table)->first()),
 * which would give every document validation the same stages. Validation types need their own
 * chains, so this class overrides first-stage selection to read the workflow off the validation's
 * type instead. Everything after that needs no help: advanceToNextStage() walks the chain by the
 * WorkFlowId on the current stage row, and t_WorkFlowPending / t_WorkFlowHistory are keyed by
 * (Source = table, SourceID = validation Id), so two types can never see each other's stages.
 *
 * Stage approvers come from each stage's permission, resolved by p_ProcessWorkflowPending - the
 * standard engine path. The type's SpecialPermission approver roster
 * (ValidationTypeService::addApprover) is deliberately NOT used for stage routing; it still governs
 * who can see validations of a type, and it remains the approver list for types with no workflow.
 *
 * Callers must go through forValidation() before submit/approve/reject/canApprove, including the
 * inherited ensurePendingForModel() and refreshPendingForCurrentStage(), which also resolve stages.
 */
class DocumentValidationWorkflow extends ApprovalWorkflow
{
    public const CODE_ID = 'DocumentValidationStatus';

    private ?int $workFlowId = null;

    public function __construct()
    {
        parent::__construct(self::CODE_ID, 'Status');
    }

    /**
     * Bind the engine to the workflow configured on this validation's type.
     */
    public function forValidation(DocumentValidation $validation): static
    {
        $this->workFlowId = $validation->type?->WorkFlowId ? (int) $validation->type->WorkFlowId : null;

        return $this;
    }

    public function submitValidation(
        DocumentValidation $validation,
        User $actor,
        string $remarks = 'Submitted for validation'
    ): bool {
        return $this->forValidation($validation)
            ->submit($validation, $actor, DocumentValidationStatusEnum::Submitted, $remarks);
    }

    public function approveValidation(DocumentValidation $validation, User $actor, string $remarks = 'Approved'): bool
    {
        return $this->forValidation($validation)
            ->approve($validation, $actor, DocumentValidationStatusEnum::Approved, $remarks, 'Status');
    }

    public function rejectValidation(DocumentValidation $validation, User $actor, string $remarks = 'Rejected'): bool
    {
        return $this->forValidation($validation)
            ->reject($validation, $actor, DocumentValidationStatusEnum::Rejected, $remarks, 'Status');
    }

    // Named canUserApprove/historyFor/statusFor rather than canApprove/history/status: the base
    // classes already declare canApprove(string, string|int, User) and history(string, int) with
    // incompatible signatures, and redeclaring those here is a fatal error.
    public function canUserApprove(DocumentValidation $validation, User $user): bool
    {
        return $this->forValidation($validation)->canApproveModel($validation, $user);
    }

    public function historyFor(DocumentValidation $validation): Collection
    {
        return $this->forValidation($validation)->historyForModel($validation);
    }

    public function statusFor(DocumentValidation $validation): array
    {
        return $this->forValidation($validation)->getStatus($validation);
    }

    /**
     * Whether the validation currently sits on the last active stage of its workflow.
     *
     * Drives both the signature requirement and the final ApprovedBy stamp: only the last
     * stage signs the document. Returns true when no workflow is bound, so the legacy
     * single-approval path still signs.
     */
    public function isFinalStage(DocumentValidation $validation): bool
    {
        $workFlowId = $validation->type?->WorkFlowId;
        if (! $workFlowId) {
            return true;
        }

        $currentStageId = $validation->workflowPending()->whereNull('DeletedOn')->latest('CreatedOn')->value('Stage');
        if (! $currentStageId) {
            return false;
        }

        $currentOrder = WorkflowStage::query()->whereKey($currentStageId)->value('Order');
        if (is_null($currentOrder)) {
            return false;
        }

        return ! WorkflowStage::query()
            ->where('WorkFlowId', $workFlowId)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->where('Order', '>', $currentOrder)
            ->exists();
    }

    public function hasPendingStage(DocumentValidation $validation): bool
    {
        return $validation->workflowPending()->whereNull('DeletedOn')->exists();
    }

    /**
     * Resolve the first stage from the validation type's own workflow rather than from the
     * single workflow the base class would find by source table.
     */
    protected function getPermissionFromStage(string $table): ?WorkflowStage
    {
        if (! $this->workFlowId) {
            return parent::getPermissionFromStage($table);
        }

        return WorkflowStage::query()
            ->where('WorkFlowId', $this->workFlowId)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->orderBy('Order', 'asc')
            ->first();
    }
}
