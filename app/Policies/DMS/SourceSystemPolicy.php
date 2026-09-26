<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;
use App\Models\DMS\SourceSystem;

class SourceSystemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function view(User $user, SourceSystem $sourceSystem): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function update(User $user, SourceSystem $sourceSystem): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function delete(User $user, SourceSystem $sourceSystem): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function restore(User $user, SourceSystem $sourceSystem): bool
    {
        return false;
    }

    public function forceDelete(User $user, SourceSystem $sourceSystem): bool
    {
        return false;
    }
}
