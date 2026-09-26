<?php

namespace App\Models\DMS;

use App\Enums\Core\VisibilityEnum;
use App\Enums\DMS\DocumentValidationModeEnum;
use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Models\Auth\User;
use App\Models\Core\Approval\WorkflowHistory;
use App\Models\Core\Approval\WorkflowPending;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentValidation extends Model
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DocumentValidations';
    protected $primaryKey = 'Id';

    protected $fillable = [
        "Name", "ValidationId", "Visibility", "ValidationTypeId", "DocumentId", "Status", "SubmissionMode",
        "ApprovedBy", "ApprovedOn", "RejectedBy", "RejectedOn", "RejectionReason",
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'Visibility'      => VisibilityEnum::class,
        'Status'          => DocumentValidationStatusEnum::class,
        'SubmissionMode'  => DocumentValidationModeEnum::class,
        'ApprovedOn'      => 'datetime',
        'RejectedOn'      => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentValidationId';
    }

    public function getRouteKeyName(): string
    {
        return 'ValidationId';
    }

    /** @return BelongsTo<DocumentValidationType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentValidationType::class, 'ValidationTypeId', 'Id')->withTrashed();
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'DocumentId');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(DocumentValidationAttribute::class, 'DocumentValidationId');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ApprovedBy', 'Id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'RejectedBy', 'Id')->withTrashed();
    }

    /** @return HasMany<WorkflowHistory, $this> */
    public function workflowHistory(): HasMany
    {
        return $this->hasMany(WorkflowHistory::class, 'SourceID', 'Id')
            ->where('Source', $this->getTable());
    }

    /** @return HasMany<WorkflowPending, $this> */
    public function workflowPending(): HasMany
    {
        return $this->hasMany(WorkflowPending::class, 'SourceID', 'Id')
            ->where('Source', $this->getTable());
    }

    public function isApproved(): bool
    {
        return $this->Status === DocumentValidationStatusEnum::Approved;
    }

    public function isRejected(): bool
    {
        return $this->Status === DocumentValidationStatusEnum::Rejected;
    }
}
