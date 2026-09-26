<?php

namespace App\Policies\Settings;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;

class SettingsPolicy
{
    /**
     * Create a new policy instance.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::ListsView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(PermissionEnum::ListsView->value);
    }
}
