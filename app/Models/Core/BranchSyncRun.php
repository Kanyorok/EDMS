<?php

namespace App\Models\Core;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;

class BranchSyncRun extends Model
{
    use UserActorTrait;

    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';

    protected $table = 't_BranchSyncRuns';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Source',
        'Status',
        'Message',
        'SyncError',
        'RecordsSynced',
        'RecordsFailed',
        'TotalRecords',
        'CurrentPage',
        'PageSize',
        'LastCursor',
        'StartedAt',
        'CompletedAt',
        'CreatedBy',
        'CreatedOn',
        'ModifiedBy',
        'ModifiedOn',
    ];

    protected $casts = [
        'StartedAt' => 'datetime',
        'CompletedAt' => 'datetime',
        'CreatedOn' => 'datetime',
        'ModifiedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'BranchSyncRunId';
    }
}
