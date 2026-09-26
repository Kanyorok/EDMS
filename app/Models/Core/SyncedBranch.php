<?php

namespace App\Models\Core;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SyncedBranch extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';
    public const DELETED_AT = 'DeletedOn';

    protected $table = 't_SyncedBranches';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'BranchID',
        'Name',
        'Address',
        'Address2',
        'City',
        'State',
        'Zip',
        'Country',
        'Phone',
        'Fax',
        'Email',
        'IsHQ',
        'IsActive',
        'Source',
        'SourceTable',
        'IsSynced',
        'CreatedBy',
        'CreatedOn',
        'ModifiedBy',
        'ModifiedOn',
        'DeletedBy',
    ];

    protected $casts = [
        'IsHQ' => 'boolean',
        'IsActive' => 'boolean',
        'IsSynced' => 'boolean',
        'CreatedOn' => 'datetime',
        'ModifiedOn' => 'datetime',
        'DeletedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'SyncedBranchId';
    }
}
