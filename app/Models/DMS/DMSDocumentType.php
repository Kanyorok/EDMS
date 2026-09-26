<?php

namespace App\Models\DMS;

use App\Enums\Core\ExtensionsEnum;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A document type's upload rules. "Category" is the repository a document of
 * this type files into and "Record classification" is the tag it inherits -
 * both existing DMS concepts, not new ones of their own.
 */
class DMSDocumentType extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSDocumentTypes';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Code', 'Name', 'RepositoryId', 'TagId', 'EntityApplicability',
        'AllowedExtensions', 'MaxSizeMB', 'ExpiryRequired', 'IsActive',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'RepositoryId' => 'integer',
        'TagId' => 'integer',
        'MaxSizeMB' => 'integer',
        'ExpiryRequired' => 'boolean',
        'IsActive' => 'boolean',
    ];

    public static function getPrimaryKey(): string
    {
        return 'DocumentTypeId';
    }

    public function getRouteKeyName(): string
    {
        return 'Id';
    }

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class, 'RepositoryId', 'Id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(DMSTags::class, 'TagId', 'Id');
    }

    /**
     * Which Linked-to Type(s) this Document Type may be used against - the
     * upload form's Linked-to Type dropdown is filtered to this set once a
     * Document Type is chosen.
     */
    public function linkedToTypes(): BelongsToMany
    {
        return $this->belongsToMany(LinkedToType::class, 't_DMSDocumentTypeLinkedToTypes', 'DocumentTypeId', 'LinkedToTypeId', 'Id', 'Id')
            ->withPivot(['CreatedBy', 'ModifiedBy'])->withTimestamps()->using(DocumentTypeLinkedToType::class);
    }

    public function extensions(): Collection
    {
        return Str::of((string) $this->AllowedExtensions)
            ->explode(',')
            ->map(fn ($ext) => Str::of($ext)->trim()->ltrim('.')->lower()->toString())
            ->filter();
    }

    /**
     * The MIME types this type's extensions stand for. "jpg" is accepted for "jpeg" (the enum only knows the
     * latter, but "jpg" is what people and the seeded types write); an extension the system has no MIME type for
     * is skipped rather than allowed to break every upload of this type with a 500.
     *
     * @return array<int, string> empty when the type names no extension at all
     */
    public function mimeTypes(): array
    {
        return $this->extensions()
            ->map(fn ($ext) => ExtensionsEnum::tryFrom($ext === 'jpg' ? 'jpeg' : $ext)?->getMimeType())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Next "{Code}-0001"-style folio code for this type.
     */
    public function nextFolio(): string
    {
        $prefix = $this->Code . '-';

        $existingCodes = DMSFolio::withTrashed()
            ->where('Code', 'LIKE', $prefix . '%')
            ->pluck('Code');

        $maxNumber = 0;
        foreach ($existingCodes as $code) {
            $suffix = substr($code, strlen($prefix));
            if (is_numeric($suffix)) {
                $num = (int) $suffix;
                if ($num > $maxNumber) {
                    $maxNumber = $num;
                }
            }
        }

        do {
            $maxNumber++;
            $candidate = $prefix . str_pad((string) $maxNumber, 4, '0', STR_PAD_LEFT);
        } while (DMSFolio::withTrashed()->where('Code', $candidate)->exists());

        return $candidate;
    }
}
