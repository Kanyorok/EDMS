<?php

namespace App\Models\Core\Approval;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowCorrection extends Model
{
    use SoftDeletes;

    public const CREATED_AT = 'CreatedOn';

    public const UPDATED_AT = 'ModifiedOn';

    public const DELETED_AT = 'DeletedOn';

    protected $table = 't_WorkFlowCorrections';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'Source', 'SourceID', 'FromStageId', 'ReturnToStageId', 'SubmitterId',
        'ReturnedBy', 'Comments', 'Status', 'ReturnedOn', 'ResubmittedBy',
        'ResubmittedOn', 'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'ReturnedOn' => 'datetime',
        'ResubmittedOn' => 'datetime',
        'CreatedOn' => 'datetime',
        'ModifiedOn' => 'datetime',
        'DeletedOn' => 'datetime',
    ];

    public function fromStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'FromStageId', 'Id');
    }

    public function returnToStage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'ReturnToStageId', 'Id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'SubmitterId', 'Id');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ReturnedBy', 'Id');
    }

    public function resubmittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ResubmittedBy', 'Id');
    }
}
