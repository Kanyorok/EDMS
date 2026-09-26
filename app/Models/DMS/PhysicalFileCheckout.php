<?php

namespace App\Models\DMS;

use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalFileCheckout extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSPhysicalFileCheckouts';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'PhysicalFileId', 'RequestId', 'BorrowerUserId', 'IssuedByUserId',
        'CheckedOutOn', 'DueOn', 'CheckOutRemark', 'CheckoutStatusId',
        'ClosedOn', 'ReturnedOn', 'ReturnedToLocationId', 'ReceivedByUserId',
        'CheckInRemark', 'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'PhysicalFileId' => 'integer',
        'RequestId' => 'integer',
        'BorrowerUserId' => 'integer',
        'IssuedByUserId' => 'integer',
        'CheckoutStatusId' => 'integer',
        'ReturnedToLocationId' => 'integer',
        'ReceivedByUserId' => 'integer',
        'CheckedOutOn' => 'datetime',
        'DueOn' => 'datetime',
        'ClosedOn' => 'datetime',
        'ReturnedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalFileCheckoutId';
    }

    /** @return BelongsTo<PhysicalFile, $this> */
    public function physicalFile(): BelongsTo
    {
        return $this->belongsTo(PhysicalFile::class, 'PhysicalFileId', 'Id');
    }

    /** @return BelongsTo<PhysicalFileRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PhysicalFileRequest::class, 'RequestId', 'Id');
    }

    /** @return BelongsTo<User, $this> */
    public function borrower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'BorrowerUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'IssuedByUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ReceivedByUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<StorageLocation, $this> */
    public function returnedTo(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'ReturnedToLocationId', 'Id');
    }

    /** @return BelongsTo<CodeDetail, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'CheckoutStatusId', 'ID');
    }

    /** @return HasMany<PhysicalFileMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(PhysicalFileMovement::class, 'CheckoutId', 'Id');
    }
}
