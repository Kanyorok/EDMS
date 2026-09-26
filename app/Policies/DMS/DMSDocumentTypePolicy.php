<?php

namespace App\Policies\DMS;

use App\Enums\Core\PermissionEnum;
use App\Models\Auth\User;
use App\Models\DMS\DMSDocumentType;

class DMSDocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function view(User $user, DMSDocumentType $documentType): bool
    {
        return $user->can(PermissionEnum::DMSView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function update(User $user, DMSDocumentType $documentType): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function delete(User $user, DMSDocumentType $documentType): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value);
    }

    public function restore(User $user, DMSDocumentType $documentType): bool
    {
        return false;
    }

    public function forceDelete(User $user, DMSDocumentType $documentType): bool
    {
        return false;
    }
}
