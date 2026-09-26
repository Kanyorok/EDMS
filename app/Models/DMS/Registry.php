<?php

namespace App\Models\DMS;

use App\Enums\Core\VisibilityEnum;
use App\Interfaces\SpecialPermissionContract;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A department that custodies physical records. Registries may nest, so an HQ
 * registry can parent its branch registries.
 */
class Registry extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSRegistries';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Code', 'Name', 'Description', 'ParentRegistryId', 'BranchId', 'HodUserId',
        'IsArchive', 'RegistryStatusId', 'Visibility',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'ParentRegistryId' => 'integer',
        'BranchId' => 'integer',
        'HodUserId' => 'integer',
        'RegistryStatusId' => 'integer',
        'IsArchive' => 'boolean',
        'Visibility' => VisibilityEnum::class,
    ];

    public static function getPrimaryKey(): string
    {
        return 'RegistryId';
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'ParentRegistryId', 'Id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'ParentRegistryId', 'Id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(StorageLocation::class, 'RegistryId', 'Id');
    }

    public function physicalFiles(): HasMany
    {
        return $this->hasMany(PhysicalFile::class, 'HomeRegistryId', 'Id');
    }

    /**
     * Nodes standing directly in the registry, i.e. with no parent location.
     */
    public function rootLocations(): HasMany
    {
        return $this->locations()->whereNull('ParentId');
    }

    public function hod(): BelongsTo
    {
        return $this->belongsTo(User::class, 'HodUserId', 'Id')->withTrashed();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'RegistryStatusId', 'ID');
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: #permission permission to ' . $this->Name;
    }
}
