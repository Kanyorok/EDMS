<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business entity type (Account, Application, Collateral, Customer, Loan, ...)
 * that a document can be linked to.
 */
class LinkedToType extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSLinkedToTypes';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Code', 'Name', 'Description', 'SourceRelation', 'IsActive',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'IsActive' => 'boolean',
    ];

    public static function getPrimaryKey(): string
    {
        return 'LinkedToTypeId';
    }

    public function getRouteKeyName(): string
    {
        return 'Id';
    }

    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DMSDocumentType::class, 't_DMSDocumentTypeLinkedToTypes', 'LinkedToTypeId', 'DocumentTypeId', 'Id', 'Id')
            ->withPivot(['CreatedBy', 'ModifiedBy'])->withTimestamps()->using(DocumentTypeLinkedToType::class);
    }
}
