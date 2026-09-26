<?php

namespace App\Models\DMS;

use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Chain-of-custody event; only its receipt fields may be completed later. */
class PhysicalFileMovement extends Model
{
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    protected $table = 't_DMSPhysicalFileMovements';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'PhysicalFileId', 'RequestId', 'CheckoutId',
        'FromRegistryId', 'FromLocationId', 'FromHolderUserId',
        'ToRegistryId', 'ToLocationId', 'ToHolderUserId',
        'MovementTypeId', 'Reason', 'DispatchedByUserId', 'DispatchedOn',
        'ReceivedByUserId', 'ReceivedOn', 'CreatedBy', 'ModifiedBy',
    ];

    protected $casts = [
        'PhysicalFileId' => 'integer',
        'RequestId' => 'integer',
        'CheckoutId' => 'integer',
        'FromRegistryId' => 'integer',
        'FromLocationId' => 'integer',
        'FromHolderUserId' => 'integer',
        'ToRegistryId' => 'integer',
        'ToLocationId' => 'integer',
        'ToHolderUserId' => 'integer',
        'MovementTypeId' => 'integer',
        'DispatchedByUserId' => 'integer',
        'ReceivedByUserId' => 'integer',
        'DispatchedOn' => 'datetime',
        'ReceivedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalFileMovementId';
    }

    public function physicalFile(): BelongsTo
    {
        return $this->belongsTo(PhysicalFile::class, 'PhysicalFileId', 'Id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PhysicalFileRequest::class, 'RequestId', 'Id');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(PhysicalFileCheckout::class, 'CheckoutId', 'Id');
    }

    public function movementType(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'MovementTypeId', 'ID');
    }

    public function fromRegistry(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'FromRegistryId', 'Id');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'FromLocationId', 'Id');
    }

    public function fromHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'FromHolderUserId', 'Id')->withTrashed();
    }

    public function toRegistry(): BelongsTo
    {
        return $this->belongsTo(Registry::class, 'ToRegistryId', 'Id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'ToLocationId', 'Id');
    }

    public function toHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ToHolderUserId', 'Id')->withTrashed();
    }

    public function dispatchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'DispatchedByUserId', 'Id')->withTrashed();
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ReceivedByUserId', 'Id')->withTrashed();
    }
}
