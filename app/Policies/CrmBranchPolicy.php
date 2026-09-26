<?php

namespace App\Policies;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;
use App\Models\Core\Branch;

class CrmBranchPolicy
{
    public function before(User $user, string $ability): bool|null
    {
        // Return null to fall through to the individual policy methods.
        // Returning false here would unconditionally deny ALL ability checks
        // before viewAny(), create(), update(), delete() ever run.
        return null;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::BranchView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Branch $crmBranch): bool
    {
        return $user->can(PermissionEnum::BranchView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::BranchCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Branch $crmBranch): bool
    {
        return $user->can(PermissionEnum::BranchUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Branch $crmBranch): bool
    {
        return $user->can(PermissionEnum::BranchDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Branch $crmBranch): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Branch $crmBranch): bool
    {
        return false;
    }
}
