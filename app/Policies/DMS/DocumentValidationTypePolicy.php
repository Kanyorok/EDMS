<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Models\Auth\User;
use App\Models\DMS\DocumentValidationType;

class DocumentValidationTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DocumentValidationType $documentValidationType): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
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
    public function update(User $user, DocumentValidationType $documentValidationType): bool
    {
        return $user->can(PermissionEnum::DMSBulkUpload->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DocumentValidationType $documentValidationType): bool
    {
        return $this->admin($user, $documentValidationType);
    }

    /**
     * Determine whether the user has admin role on the model.
     */
    public function admin(User $user, DocumentValidationType $documentValidationType): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return DocumentValidationType::query()->userRole($user, [RoleEnum::Admin->value])->where('Id', $documentValidationType->Id)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, DocumentValidationType $documentValidationType): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, DocumentValidationType $documentValidationType): bool
    {
        return false;
    }
}
