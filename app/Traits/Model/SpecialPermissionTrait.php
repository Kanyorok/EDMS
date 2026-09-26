<?php

namespace App\Traits\Model;

use App\Enums\Core\RoleEnum;
use App\Enums\Core\VisibilityEnum;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Core\SpecialPermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait SpecialPermissionTrait
{
    public function permissions(): MorphMany
    {
        return $this->morphMany(SpecialPermission::class, 'model', "Model", "ModelID", 'Id');
    }

    public function scopeUser(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $query) use ($user) {
            $query->where($this->getTable() . '.Visibility', VisibilityEnum::Public->value)
                ->orWhereHas('permissions', function (Builder $q) use ($user) {
                    $q->where(function (Builder $q) use ($user) {
                        $q->where('t_SpecialPermissions.Party', User::getPrimaryKey())
                            ->where('t_SpecialPermissions.PartyID', $user->Id);
                    })->orWhere(function (Builder $q) use ($user) {
                        $q->where('t_SpecialPermissions.Party', Team::getPrimaryKey())
                            ->whereIn('t_SpecialPermissions.PartyID', $user->teams()->select('t_Teams.TeamID'));
                    });
                });
        });
    }

    /**
     * Scope a query to include only models that the user has a specific role or permission for.
     *
     * @param Builder $q
     * @param User $user
     * @param array $permissions [RoleEnum::Read->value, ...]
     * @return Builder
     */
    public function scopeUserRole(Builder $q, User $user, array $permissions): Builder
    {
        return $q->where(function (Builder $query) use ($permissions, $user) {
            $query->where($this->getTable() . '.Visibility', VisibilityEnum::Public->value)
                ->orWhereHas('permissions', function (Builder $q) use ($user, $permissions) {
                    $q->where(function (Builder $q) use ($permissions, $user) {
                        $q->where('t_SpecialPermissions.Party', User::getPrimaryKey())
                            ->whereIn('t_SpecialPermissions.Permission', $permissions)
                            ->where('t_SpecialPermissions.PartyID', $user->Id);
                    })->orWhere(function (Builder $q) use ($permissions, $user) {
                        $q->where('t_SpecialPermissions.Party', Team::getPrimaryKey())
                            ->whereIn('t_SpecialPermissions.Permission', $permissions)
                            ->whereIn('t_SpecialPermissions.PartyID', $user->teams()->select('t_Teams.TeamID'));
                    });
                });
        });
    }

    /**
     * Rows this user was actually granted one of these roles on.
     *
     * The counterpart to scopeUserRole(), which also matches public rows. That
     * is right for reads - a public thing is meant to be seen - but it must
     * never be read as authority: a registry is public so that everybody can
     * find it, not so that everybody can administer it. Use this scope
     * wherever the question is "may they act", not "may they look".
     *
     * @param  array<int,string>  $permissions  [RoleEnum::Admin->value, ...]
     * @return Builder<static>
     */
    public function scopeUserGrant(Builder $q, User $user, array $permissions): Builder
    {
        return $q->whereHas('permissions', function (Builder $query) use ($user, $permissions) {
            $query->where(function (Builder $q) use ($permissions, $user) {
                $q->where('t_SpecialPermissions.Party', User::getPrimaryKey())
                    ->whereIn('t_SpecialPermissions.Permission', $permissions)
                    ->where('t_SpecialPermissions.PartyID', $user->Id);
            })->orWhere(function (Builder $q) use ($permissions, $user) {
                $q->where('t_SpecialPermissions.Party', Team::getPrimaryKey())
                    ->whereIn('t_SpecialPermissions.Permission', $permissions)
                    ->whereIn('t_SpecialPermissions.PartyID', $user->teams()->select('t_Teams.TeamID'));
            });
        });
    }

    public function getSharedName(): string
    {
        return $this->Name;
    }

    public function scopeUserCreator(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $query) use ($user) {
            $query->where($this->getTable() . '.Visibility', VisibilityEnum::Public->value)
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where($this->getTable() . '.Visibility', VisibilityEnum::Private->value)
                        ->where($this->getTable() . '.CreatedBy', $user->Id);
                });
        });
    }

    /**
     * #permission -> role / permission name eg Read, Write ...
     */
    /*  abstract public function getShareEmailSubject(): string;*/
}
