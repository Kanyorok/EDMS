<?php

namespace App\Models\DMS;

use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A business occasion in an external system that produces a document. It offers a list of
 * document types (each with its own upload rules), the kind of external record the file belongs
 * to, the external screens that may raise it and the properties the user supplies.
 */
class EventType extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSEventTypes';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'SourceSystemId', 'Code', 'Name', 'Description', 'LinkedToTypeId',
        'ExternalEntityType', 'AllowReplace', 'ReviewerUserId', 'ReviewerTeamId', 'ValidationTypeId', 'IsActive',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'SourceSystemId' => 'integer',
        'LinkedToTypeId' => 'integer',
        'ReviewerUserId' => 'integer',
        'ReviewerTeamId' => 'integer',
        'ValidationTypeId' => 'integer',
        'AllowReplace' => 'boolean',
        'IsActive' => 'boolean',
    ];

    public static function getPrimaryKey(): string
    {
        return 'EventTypeId';
    }

    public function getRouteKeyName(): string
    {
        return 'Id';
    }

    public function sourceSystem(): BelongsTo
    {
        return $this->belongsTo(SourceSystem::class, 'SourceSystemId', 'Id');
    }

    /**
     * The document types a person may file under this event; the one they pick decides the upload rules
     * (extensions, size, expiry) and the repository.
     */
    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DMSDocumentType::class, 't_DMSEventDocumentTypes', 'EventTypeId', 'DocumentTypeId', 'Id', 'Id')
            ->withPivot(['CreatedBy', 'ModifiedBy'])->withTimestamps('CreatedOn', 'ModifiedOn')
            ->whereNull('t_DMSDocumentTypes.DeletedOn');
    }

    public function linkedToType(): BelongsTo
    {
        return $this->belongsTo(LinkedToType::class, 'LinkedToTypeId', 'Id');
    }

    /** Default reviewer when the event asks for a review and the caller names no one: a user... */
    public function reviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ReviewerUserId', 'Id');
    }

    /** ...or a team, whose members share the review. */
    public function reviewerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'ReviewerTeamId', 'TeamID');
    }

    public function validationType(): BelongsTo
    {
        return $this->belongsTo(DocumentValidationType::class, 'ValidationTypeId', 'Id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(EventModuleMap::class, 'EventTypeId', 'Id');
    }

    public function properties(): HasMany
    {
        return $this->hasMany(EventProperty::class, 'EventTypeId', 'Id')->orderBy('SortOrder')->orderBy('Id');
    }
}
