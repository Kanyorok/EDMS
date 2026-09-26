<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhysicalDocument extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';

    public const string UPDATED_AT = 'ModifiedOn';

    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSPhysicalDocuments';

    protected $primaryKey = 'Id';

    protected $fillable = [
        'PhysicalFileId', 'DocumentId', 'ReferenceNumber', 'Title',
        'DocumentDate', 'SortOrder', 'Description', 'Notes',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'PhysicalFileId' => 'integer',
        'DocumentId' => 'integer',
        'DocumentDate' => 'date',
        'SortOrder' => 'integer',
    ];

    public static function getPrimaryKey(): string
    {
        return 'PhysicalDocumentId';
    }

    /** @return BelongsTo<PhysicalFile, $this> */
    public function physicalFile(): BelongsTo
    {
        return $this->belongsTo(PhysicalFile::class, 'PhysicalFileId', 'Id');
    }

    /** @return BelongsTo<Document, $this> */
    public function digitalDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'DocumentId', 'Id');
    }

    /** @return HasMany<PhysicalDocumentRelation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(PhysicalDocumentRelation::class, 'PhysicalDocumentId', 'Id');
    }
}
