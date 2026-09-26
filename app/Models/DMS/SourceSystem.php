<?php

namespace App\Models\DMS;

use App\Models\Auth\User;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An external system (core banking, ...) that sends documents into the DMS.
 * Uploads it makes are attributed to ServiceUserId.
 */
class SourceSystem extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_DMSSourceSystems';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'Code', 'Name', 'Description', 'ServiceUserId', 'IsActive',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];

    protected $casts = [
        'ServiceUserId' => 'integer',
        'IsActive' => 'boolean',
    ];

    public static function getPrimaryKey(): string
    {
        return 'SourceSystemId';
    }

    public function getRouteKeyName(): string
    {
        return 'Id';
    }

    public function serviceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ServiceUserId', 'Id')->withTrashed();
    }

    public function eventTypes(): HasMany
    {
        return $this->hasMany(EventType::class, 'SourceSystemId', 'Id');
    }
}
