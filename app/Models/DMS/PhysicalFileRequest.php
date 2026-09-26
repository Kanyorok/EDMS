<?php

namespace App\Models\DMS;

use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalFileRequest extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSPhysicalFileRequests';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'PhysicalFileId', 'RequestedByUserId', 'RequestedOn', 'NeededFrom',
        'RequestedUntil', 'Purpose', 'RequestStatusId', 'DecidedByUserId',
        'DecidedOn', 'DecisionRemark', 'CancelledByUserId', 'CancelledOn',
        'CancellationReason', 'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'PhysicalFileId' => 'integer',
        'RequestedByUserId' => 'integer',
        'RequestStatusId' => 'integer',
        'DecidedByUserId' => 'integer',
        'CancelledByUserId' => 'integer',
        'RequestedOn' => 'datetime',
        'NeededFrom' => 'datetime',
        'RequestedUntil' => 'datetime',
        'DecidedOn' => 'datetime',
        'CancelledOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalFileRequestId';
    }

    /** @return BelongsTo<PhysicalFile, $this> */
    public function physicalFile(): BelongsTo
    {
        return $this->belongsTo(PhysicalFile::class, 'PhysicalFileId', 'Id');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'RequestedByUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'DecidedByUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'CancelledByUserId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<CodeDetail, $this> */
    public function status(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'RequestStatusId', 'ID');
    }

    /** @return HasOne<PhysicalFileCheckout, $this> */
    public function checkout(): HasOne
    {
        return $this->hasOne(PhysicalFileCheckout::class, 'RequestId', 'Id');
    }
}
