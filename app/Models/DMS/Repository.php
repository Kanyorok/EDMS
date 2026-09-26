<?php

namespace App\Models\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\VisibilityEnum;
use App\Interfaces\SpecialPermissionContract;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Repository extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_Repositories';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Name', 'Description', 'RepositoryId', 'Visibility', 'ParentId',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'Visibility' => VisibilityEnum::class,
        'ParentId' => 'integer',
    ];

    public static function getPrimaryKey(): string
    {
        return 'RepositoryId';
    }

    public function getRouteKeyName(): string
    {
        return 'RepositoryId';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'RepositoryId', 'Id');
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(__CLASS__, 'ParentId', 'Id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'ParentId', 'Id');
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: #permission permission to ' . $this->Name;
    }

    /**
     * Overrides SpecialPermissionTrait::scopeUser() to add an Admin-permission
     * bypass ahead of the usual public/grant check.
     *
     * Private repositories created by system bootstrap code (RepositoryService
     * ::internal()/module()) never get a permission row for anyone - there is
     * no human actor in that flow to grant one to - so without this bypass
     * they are permanently unreachable by every user, including the account
     * that "created" them. The admin permission is the same authority
     * RegistryPolicy/StorageLocationPolicy already let bypass per-record
     * grants for administering a node; extending it to viewing here means no
     * repository can end up orphaned.
     */
    public function scopeUser(Builder $q, User $user): Builder
    {
        if ($user->can(PermissionEnum::Admin->value)) {
            return $q;
        }

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
}
