<?php

namespace App\Models\DMS;

use App\Enums\Core\VisibilityEnum;
use App\Interfaces\SpecialPermissionContract;
use App\Models\Core\Approval\Workflow;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentValidationType extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DocumentValidationTypes';
    protected $primaryKey = 'Id';

    protected $fillable = [
        "ValidationTypeId", "Name", "Notes", "Visibility", "WorkFlowId",
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'Visibility' => VisibilityEnum::class,
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentValidationTypeId';
    }

    public function getRouteKeyName(): string
    {
        return 'ValidationTypeId';
    }

    public function validations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class, 'ValidationTypeId', 'Id');
    }

    /**
     * The approval workflow documents of this type must pass through.
     *
     * Null means no workflow is bound and the type keeps the legacy behaviour: a single
     * approver drawn from the type's SpecialPermission roster approves and signs in one step.
     */
    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class, 'WorkFlowId', 'Id');
    }

    public function hasWorkflow(): bool
    {
        return ! is_null($this->WorkFlowId);
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: Added as default document validator to  #' . $this->ValidationTypeId;
    }

    public function getSharedName(): string
    {
        return "#" . $this->ValidationTypeId;
    }
}
