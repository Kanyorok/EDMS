<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Models\Auth\User;
use App\Models\DMS\StorageLocation;

/**
 * Permission side of storage-location management.
 *
 * Branch containment is a separate concern and is enforced by
 * RegistryAccessService: passing this policy means the user may manage
 * shelving, not that they may manage *this* branch's shelving.
 */
class StorageLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function view(User $user, StorageLocation $storageLocation): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    public function update(User $user, StorageLocation $storageLocation): bool
    {
        return $this->manages($user);
    }

    /**
     * Removing shelving needs both the module right and admin over the node.
     */
    public function delete(User $user, StorageLocation $storageLocation): bool
    {
        return $this->manages($user) && $this->admin($user, $storageLocation);
    }

    /**
     * Authority over a location, which is not the same as sight of one.
     *
     * Shelving inside a public registry is itself public so that files can be
     * found; userGrant() rather than userRole() keeps that from being read as
     * a licence to administer it.
     */
    public function admin(User $user, StorageLocation $storageLocation): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return StorageLocation::query()
            ->userGrant($user, [RoleEnum::Admin->value])
            ->where('Id', $storageLocation->Id)
            ->exists();
    }

    public function restore(User $user, StorageLocation $storageLocation): bool
    {
        return false;
    }

    /**
     * May the user manage shelving at all, in any registry.
     */
    private function manages(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value)
            || $user->can(PermissionEnum::Admin->value);
    }

    public function forceDelete(User $user, StorageLocation $storageLocation): bool
    {
        return false;
    }
}
