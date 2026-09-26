<?php

namespace App\Models\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One field of an event's upload form. The value a user enters is stored on the document
 * as a DocumentAttribute named after Name.
 */
class EventProperty extends Model
{
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';

    protected $table = 't_DMSEventProperties';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'EventTypeId', 'Name', 'Label', 'DataType', 'IsRequired', 'Options', 'SortOrder',
        'CreatedBy', 'ModifiedBy',
    ];

    protected $casts = [
        'EventTypeId' => 'integer',
        'DataType' => DataTypesEnum::class,
        'IsRequired' => 'boolean',
        'Options' => 'array',
        'SortOrder' => 'integer',
    ];

    public static function getPrimaryKey(): string
    {
        return 'EventPropertyId';
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'EventTypeId', 'Id');
    }
}
