<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalDocumentRelation extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSPhysicalDocumentRelations';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'PhysicalDocumentId', 'Related', 'RelatedID',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalDocumentRelationId';
    }

    public function physicalDocument(): BelongsTo
    {
        return $this->belongsTo(PhysicalDocument::class, 'PhysicalDocumentId', 'Id');
    }

    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'Related', 'RelatedID');
    }
}
