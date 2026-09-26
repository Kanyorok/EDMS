<?php

namespace App\Models\DMS;

use App\Models\Core\Approval\CodeDetail;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One legal nesting arrangement, e.g. a shelf may sit inside a cabinet.
 * A null ParentTypeId means the child type may stand in the registry itself.
 *
 * The rule set is system-wide and advisory at the database level; it is
 * enforced by StorageLocationService on every create and move.
 */
class LocationTypeRule extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSLocationTypeRules';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'ParentTypeId', 'ChildTypeId', 'IsActive',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'ParentTypeId' => 'integer',
        'ChildTypeId' => 'integer',
        'IsActive' => 'boolean',
    ];

    public static function getPrimaryKey(): string
    {
        return 'LocationTypeRuleId';
    }

    public function parentType(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'ParentTypeId', 'ID');
    }

    public function childType(): BelongsTo
    {
        return $this->belongsTo(CodeDetail::class, 'ChildTypeId', 'ID');
    }
}
