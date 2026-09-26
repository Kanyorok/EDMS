<?php

namespace App\Models\DMS;

use App\Enums\Core\ExtensionsEnum;
use App\Enums\Core\VisibilityEnum;
use App\Exceptions\ErroredException;
use App\Interfaces\SpecialPermissionContract;
use App\Models\Auth\User;
use App\Models\Core\CategoryMaster;
use App\Traits\Model\SpecialPermissionTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\DMS\DocumentValidation;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model implements SpecialPermissionContract
{
    use SoftDeletes;
    use UserActorTrait;
    use SpecialPermissionTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_Documents';
    protected $primaryKey = 'Id';

    protected $fillable = [
        "DocumentId", "Name", "MimeType", "CategoryId", "RepositoryId", "Visibility",
        "DocumentTypeId", "LinkedToTypeId", "Related", "LinkedId", "FolioId", "Notes", "ExpiryDate",
        "SourceSystemId", "EventTypeId", "SourceModuleId", "ExternalEntityType", "ExternalEntityId",
        "ArchivedOn", "ArchivedBy", "ArchiveReason",
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'RepositoryId' => 'integer',
        'DocumentTypeId' => 'integer',
        'LinkedToTypeId' => 'integer',
        'FolioId' => 'integer',
        'SourceSystemId' => 'integer',
        'EventTypeId' => 'integer',
        'SourceModuleId' => 'integer',
        'Visibility' => VisibilityEnum::class,
        'ExpiryDate' => 'date',
        'ArchivedOn' => 'datetime',
    ];

    public function ext(): ?ExtensionsEnum
    {
        try {
            return ExtensionsEnum::fromMimeType($this->MimeType);
        } catch (ErroredException) {
            return null;
        }
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(CategoryMaster::class, 'CategoryId', 'Id')->withAttributes(['Type' => self::getPrimaryKey()]);
    }

    public static function getPrimaryKey(): string
    {
        return 'DocumentId';
    }

    public function getRouteKeyName(): string
    {
        return self::getPrimaryKey();
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'RepositoryId', 'Id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DMSDocumentType::class, 'DocumentTypeId', 'Id');
    }

    public function linkedToType(): BelongsTo
    {
        return $this->belongsTo(LinkedToType::class, 'LinkedToTypeId', 'Id');
    }

    public function folio(): BelongsTo
    {
        return $this->belongsTo(DMSFolio::class, 'FolioId', 'Id');
    }

    public function archiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ArchivedBy', 'Id')->withTrashed();
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DMSTags::class, 't_DocumentTags', 'DocId', 'TagId', $this->primaryKey, 'Id')
            ->withPivot(['CreatedBy', 'ModifiedBy', 'DeletedBy'])->withTimestamps()->whereNull('t_DocumentTags.DeletedOn')
            ->using(DocumentTags::class);
    }

    public function sourceSystem(): BelongsTo
    {
        return $this->belongsTo(SourceSystem::class, 'SourceSystemId', 'Id');
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'EventTypeId', 'Id');
    }

    public function holds(): BelongsToMany
    {
        return $this->belongsToMany(LegalHold::class, 't_DocumentLegalHolds', 'DocId', 'LegalHoldId', $this->primaryKey, 'Id')
            ->withPivot(['CreatedBy', 'ModifiedBy', 'DeletedBy'])->withTimestamps()->whereNull('t_DocumentLegalHolds.DeletedOn')
            ->using(DocumentLegalHold::class);
    }

    public function relations(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'DocumentId', 'Id')
            ->whereNull('DeletedOn');
    }

    public function current(): HasOne
    {
        return $this->hasOne(DocumentVersion::class, 'DocumentId', 'Id')->latest('t_DocumentVersions.Id');
    }

    public function checkouts(): HasMany
    {
        return $this->hasMany(DocumentCheckOut::class, 'DocumentId', 'Id');
    }

    public function physicalDocuments(): HasMany
    {
        return $this->hasMany(PhysicalDocument::class, 'DocumentId', 'Id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'DocumentId', 'Id');
    }

    public function validations(): HasMany
    {
        return $this->hasMany(DocumentValidation::class, 'DocumentId', 'Id')->whereNull('DeletedOn');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class, 'DocumentId', 'Id')->whereNull('DeletedOn');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(DocumentAttribute::class, 'DocumentId', 'Id');
    }

    public function getShareEmailSubject(): string
    {
        return 'Notification: #permission permission to ' . $this->Name;
    }
}
