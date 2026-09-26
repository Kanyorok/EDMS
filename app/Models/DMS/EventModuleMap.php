<?php

namespace App\Models\DMS;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ties an event to a screen (module ID) in the external system, so the screen can ask
 * "which events can I raise here?".
 */
class EventModuleMap extends Model
{
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';

    protected $table = 't_DMSEventModuleMaps';
    protected $primaryKey = 'Id';

    protected $fillable = ['EventTypeId', 'SourceModuleId', 'CreatedBy', 'ModifiedBy'];

    protected $casts = [
        'EventTypeId' => 'integer',
        'SourceModuleId' => 'integer',
    ];

    public static function getPrimaryKey(): string
    {
        return 'EventModuleMapId';
    }

    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'EventTypeId', 'Id');
    }
}
