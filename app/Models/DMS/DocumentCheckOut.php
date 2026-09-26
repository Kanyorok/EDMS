<?php

namespace App\Models\DMS;

use App\Enums\DMS\DocumentCheckOutStatusEnum;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentCheckOut extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DocumentCheckOuts';
    protected $primaryKey = 'Id';

    protected $fillable = [
        "DocumentId", "CheckOutRemark", "CheckInRemark", "Status", "Dated",
        "RejectionReminderCount", "RejectionReminderLastSentOn",
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'DocumentId' => 'integer',
        'CreatedBy' => 'integer',
        'Dated' => 'datetime',
        'Status' => DocumentCheckOutStatusEnum::class,
        'RejectionReminderCount' => 'integer',
        'RejectionReminderLastSentOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentCheckOutId';
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'DocumentId', 'Id');
    }
}
