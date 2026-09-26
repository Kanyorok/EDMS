<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DocumentTypeLinkedToType extends Pivot
{
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';

    protected $table = 't_DMSDocumentTypeLinkedToTypes';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'DocumentTypeId', 'LinkedToTypeId', 'CreatedBy', 'ModifiedBy',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentTypeLinkedToType';
    }
}
