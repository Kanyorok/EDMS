<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Enums\Core\VisibilityEnum;
use App\Models\Auth\User;
use App\Models\DMS\Repository;

class RepositoryPolicy
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
    public function view(User $user, Repository $repository): bool
    {
        return Repository::query()->user($user)->where('Id', $repository->Id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSBulkUpload->value);
    }

    /**
     * Determine whether the user can update the model.if public creator or admin
     *
     * This also gates adding/removing permissions and changing visibility
     * (RepositoryPermissionController::store/destroy/visibility all authorize
     * against 'update'), so the Admin-permission bypass here is what lets an
     * admin actually grant others access to an otherwise-orphaned repository
     * such as Internal or Third Parties, not just view it.
     */
    public function update(User $user, Repository $repository): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        if (($repository->Visibility->value === VisibilityEnum::Public->value) && ($repository->CreatedBy === $user->Id)) {
            return true;
        }

        return Repository::query()->userRole($user, [RoleEnum::Admin->value, RoleEnum::Share->value, RoleEnum::Write->value])->where('Id', $repository->Id)->exists();
    }

    public function share(User $user, Repository $repository): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return Repository::query()->userRole($user, [RoleEnum::Admin->value, RoleEnum::Share->value])->where('Id', $repository->Id)->exists();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Repository $repository): bool
    {
        return $this->admin($user, $repository);
    }

    public function admin(User $user, Repository $repository): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return Repository::query()->userRole($user, [RoleEnum::Admin->value])->where('Id', $repository->Id)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Repository $repository): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Repository $repository): bool
    {
        return false;
    }
}
