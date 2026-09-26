<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Models\Auth\User;
use App\Models\DMS\Registry;

/**
 * Permission side of registry management.
 *
 * Whether the user may open a given registry at all is a branch question,
 * answered by RegistryAccessService rather than here.
 */
class RegistryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function view(User $user, Registry $registry): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function update(User $user, Registry $registry): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function delete(User $user, Registry $registry): bool
    {
        return $this->admin($user, $registry);
    }

    /**
     * Authority over a registry, which is not the same as sight of one.
     *
     * Registries are public so that any user can find them and ask for a file.
     * userGrant() rather than userRole() keeps that visibility from being read
     * as a licence to administer, which would let anyone delete one.
     */
    public function admin(User $user, Registry $registry): bool
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return true;
        }

        return Registry::query()
            ->userGrant($user, [RoleEnum::Admin->value])
            ->where('Id', $registry->Id)
            ->exists();
    }

    public function restore(User $user, Registry $registry): bool
    {
        return false;
    }

    public function forceDelete(User $user, Registry $registry): bool
    {
        return false;
    }
}
