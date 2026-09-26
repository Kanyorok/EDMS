<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A case-level container grouping documents uploaded for one business
 * transaction. References documents; does not own or store them - deleting
 * a Folio must never delete the documents that reference it (see the
 * nullOnDelete FK on t_Documents.FolioId).
 */
class DMSFolio extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSFolios';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Code', 'Name', 'Description', 'Status', 'ClosedOn', 'ClosedBy',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'ClosedOn' => 'datetime',
    ];

    public static function getPrimaryKey(): string
    {
        return 'FolioId';
    }

    public function getRouteKeyName(): string
    {
        return 'Id';
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'FolioId', 'Id');
    }
}
