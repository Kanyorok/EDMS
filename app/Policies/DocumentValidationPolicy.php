<?php

namespace App\Policies;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Models\Auth\User;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;

class DocumentValidationPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->hasRole(RoleEnum::Admin->value)
            || $user->can(PermissionEnum::Admin->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentValidation $documentValidation): bool
    {
        if (! $user->can(PermissionEnum::DMSView->value)) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        // No type bound — allow any user who has DMSView to see it.
        if (is_null($documentValidation->ValidationTypeId)) {
            return true;
        }

        // Allow the creator/submitter to view their request.
        if ($documentValidation->CreatedBy === $user->Id) {
            return true;
        }

        // Allow any user who is a pending approver for the current workflow stage.
        if ($documentValidation->workflowPending()->where('UserId', $user->Id)->whereNull('DeletedOn')->exists()) {
            return true;
        }

        return DocumentValidationType::query()
            ->user($user)
            ->where('Id', $documentValidation->ValidationTypeId)
            ->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSBulkUpload->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DocumentValidation $documentValidation): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // No type — allow the original submitter to update (e.g. re-submit after rejection).
        if (is_null($documentValidation->ValidationTypeId)) {
            return $documentValidation->CreatedBy === $user->Id;
        }

        return DocumentValidationType::query()
            ->userRole($user, [RoleEnum::Admin->value, RoleEnum::Write->value])
            ->where('Id', $documentValidation->ValidationTypeId)
            ->exists();
    }

    /**
     * Determine whether the user can approve/reject the model.
     */
    public function approve(User $user, DocumentValidation $documentValidation): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // Allow any user who is a pending approver for the current workflow stage.
        if ($documentValidation->workflowPending()->where('UserId', $user->Id)->whereNull('DeletedOn')->exists()) {
            return true;
        }

        if (is_null($documentValidation->ValidationTypeId)) {
            // Typeless: allow anyone with Write-level DMS access to approve.
            return $user->can(PermissionEnum::DMSView->value);
        }

        return DocumentValidationType::query()
            ->userGrant($user, [RoleEnum::Admin->value, RoleEnum::Write->value])
            ->where('Id', $documentValidation->ValidationTypeId)
            ->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentValidation $documentValidation): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (is_null($documentValidation->ValidationTypeId)) {
            return $documentValidation->CreatedBy === $user->Id;
        }

        return DocumentValidationType::query()
            ->userRole($user, [RoleEnum::Admin->value])
            ->where('Id', $documentValidation->ValidationTypeId)
            ->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentValidation $documentValidation): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentValidation $documentValidation): bool
    {
        return false;
    }
}
