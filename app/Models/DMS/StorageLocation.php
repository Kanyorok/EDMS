<?php

namespace App\Models\DMS;

use App\Enums\Core\VisibilityEnum;
use App\Interfaces\SpecialPermissionContract;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * A node in a registry's shelving tree: cabinet, shelf, box or tray.
 *
 * Path is maintained by StorageLocationService and always carries a leading
 * and trailing slash ('/HR-REG/CAB-04/'), so a descendant test is a plain
 * prefix match with no risk of CAB-04 matching CAB-041.
 */
class StorageLocation extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSStorageLocations';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'RegistryId', 'ParentId', 'LocationTypeId', 'LocationPurposeId',
        'LocationStatusId', 'Code', 'Label', 'Path', 'Depth', 'Barcode',
        'Capacity', 'SortOrder', 'Visibility', 'InheritAccess',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'RegistryId' => 'integer',
        'ParentId' => 'integer',
        'LocationTypeId' => 'integer',
        'LocationPurposeId' => 'integer',
        'LocationStatusId' => 'integer',
        'Depth' => 'integer',
        'Capacity' => 'integer',
        'SortOrder' => 'integer',
        'InheritAccess' => 'boolean',
        'Visibility' => VisibilityEnum::class,
    ];

    public static function getPrimaryKey(): string
    {
        return 'StorageLocationId';
    }

    public function registry(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'RegistryId', 'Id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'ParentId', 'Id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'ParentId', 'Id');
    }

    public function homeFiles(): HasMany
    {
        return $this->hasMany(PhysicalFile::class, 'HomeLocationId', 'Id');
    }

    public function currentFiles(): HasMany
    {
        return $this->hasMany(PhysicalFile::class, 'CurrentLocationId', 'Id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'LocationTypeId', 'ID');
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'LocationPurposeId', 'ID');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'LocationStatusId', 'ID');
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: #permission permission to ' . $this->getSharedName();
    }

    public function getSharedName(): string
    {
        return $this->Label ?: $this->Code;
    }

    /**
     * Every descendant of this node, itself excluded.
     */
    public function scopeDescendantsOf(Builder $q, self $node): Builder
    {
        return $q->where('RegistryId', $node->RegistryId)
            ->where('Path', 'like', $node->Path . '%')
            ->where('Id', '<>', $node->Id);
    }

    /**
     * Restricts to nodes the user may see, honouring inherited grants.
     *
     * SpecialPermissionTrait::scopeUser is row-local: it only matches a grant
     * whose ModelID is this exact row. Shelving is a tree, so a grant on a
     * cabinet has to reach the shelves inside it. This scope adds that.
     *
     * A node is visible when it is public, or carries its own grant, or has an
     * ancestor carrying a grant with no InheritAccess = 0 barrier in between.
     * The barrier check is what makes a detached sub-tree private again.
     *
     * @param array<int,string> $permissions RoleEnum values; empty means any.
     */
    public function scopeAccessible(Builder $q, User $user, array $permissions = []): Builder
    {
        $table = $this->getTable();
        $teamIds = $user->teams()->select('t_Teams.TeamID');

        return $q->where(function (Builder $query) use ($table, $user, $teamIds, $permissions) {
            $query->where($table . '.Visibility', VisibilityEnum::Public->value)
                ->orWhereExists(function ($grant) use ($table, $user, $teamIds, $permissions) {
                    $grant->selectRaw('1')
                        ->from('t_DMSStorageLocations as anc')
                        ->join('t_SpecialPermissions as sp', function ($join) {
                            // ModelID is nvarchar(100); the morph stores the
                            // numeric Id in it, so the join has to cast.
                            $join->on('sp.ModelID', '=', DB::raw('CAST(anc.Id AS nvarchar(100))'))
                                ->where('sp.Model', '=', self::getPrimaryKey());
                        })
                        ->whereNull('sp.DeletedOn')
                        ->whereNull('anc.DeletedOn')
                        ->whereColumn('anc.RegistryId', $table . '.RegistryId')
                        ->where(function ($party) use ($user, $teamIds) {
                            $party->where(function ($self) use ($user) {
                                $self->where('sp.Party', User::getPrimaryKey())
                                    ->where('sp.PartyID', $user->Id);
                            })->orWhere(function ($team) use ($teamIds) {
                                $team->where('sp.Party', Team::getPrimaryKey())
                                    ->whereIn('sp.PartyID', $teamIds);
                            });
                        })
                        ->when($permissions, fn ($b) => $b->whereIn('sp.Permission', $permissions))
                        ->where(fn ($reach) => $this->applyReach($reach, $table));
                });
        });
    }

    /**
     * The grant on `anc` reaches this row either because `anc` IS this row, or
     * because `anc` is an ancestor and nothing between them detaches.
     */
    private function applyReach($query, string $table)
    {
        return $query->whereColumn('anc.Id', $table . '.Id')
            ->orWhere(function ($inherited) use ($table) {
                $inherited->where($table . '.InheritAccess', true)
                    ->whereNotNull('anc.Path')
                    ->whereNotNull($table . '.Path')
                    ->whereColumn('anc.Id', '<>', $table . '.Id')
                    ->whereRaw("{$table}.Path LIKE anc.Path + '%'")
                    ->whereNotExists(function ($barrier) use ($table) {
                        $barrier->selectRaw('1')
                            ->from('t_DMSStorageLocations as brk')
                            ->whereNull('brk.DeletedOn')
                            ->where('brk.InheritAccess', false)
                            ->whereColumn('brk.RegistryId', $table . '.RegistryId')
                            ->whereColumn('brk.Id', '<>', $table . '.Id')
                            ->whereRaw("{$table}.Path LIKE brk.Path + '%'")
                            ->whereRaw("brk.Path LIKE anc.Path + '_%'");
                    });
            });
    }
}
