<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;
use App\Models\DMS\LinkedToType;

class LinkedToTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function view(User $user, LinkedToType $linkedToType): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function update(User $user, LinkedToType $linkedToType): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function delete(User $user, LinkedToType $linkedToType): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function restore(User $user, LinkedToType $linkedToType): bool
    {
        return false;
    }

    public function forceDelete(User $user, LinkedToType $linkedToType): bool
    {
        return false;
    }
}
