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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** The paper folder/jacket that is stored, requested and loaned. */
class PhysicalFile extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use SpecialPermissionTrait;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSPhysicalFiles';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'FileNumber', 'Title', 'Description', 'Barcode',
        'HomeRegistryId', 'HomeLocationId', 'CurrentRegistryId',
        'CurrentLocationId', 'CurrentHolderUserId', 'FileStatusId',
        'OpenedOn', 'ClosedOn', 'ArchivedOn', 'Visibility',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'HomeRegistryId' => 'integer',
        'HomeLocationId' => 'integer',
        'CurrentRegistryId' => 'integer',
        'CurrentLocationId' => 'integer',
        'CurrentHolderUserId' => 'integer',
        'FileStatusId' => 'integer',
        'OpenedOn' => 'date',
        'ClosedOn' => 'date',
        'ArchivedOn' => 'datetime',
        'Visibility' => VisibilityEnum::class,
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalFileId';
    }

    public function getSharedName(): string
    {
        return $this->FileNumber.' - '.$this->Title;
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: #permission permission to '.$this->getSharedName();
    }

    /** @return BelongsTo<Registry, $this> */
    public function homeRegistry(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'HomeRegistryId', 'Id');
    }

    /** @return BelongsTo<StorageLocation, $this> */
    public function homeLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'HomeLocationId', 'Id');
    }

    public function currentRegistry(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'CurrentRegistryId', 'Id');
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'CurrentLocationId', 'Id');
    }

    public function currentHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'CurrentHolderUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<CodeDetail, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'FileStatusId', 'ID');
    }

    /** @return HasMany<PhysicalDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PhysicalDocument::class, 'PhysicalFileId', 'Id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(PhysicalFileRequest::class, 'PhysicalFileId', 'Id');
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(PhysicalFileCheckout::class, 'PhysicalFileId', 'Id');
    }

    public function activeCheckout(): HasOne
    {
        return $this->hasOne(PhysicalFileCheckout::class, 'PhysicalFileId', 'Id')
            ->whereNull('ClosedOn');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(PhysicalFileMovement::class, 'PhysicalFileId', 'Id');
    }
}
