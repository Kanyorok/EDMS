<?php

namespace App\Models\Auth;

use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;
    use UserActorTrait;

    public const string CREATED_AT = 'CreatedOn';
    public const string UPDATED_AT = 'ModifiedOn';
    public const string DELETED_AT = 'DeletedOn';

    protected $table = 't_Teams';
    protected $primaryKey = 'TeamID';

    public static function getPrimaryKey(): string
    {
        return 'TeamID';
    }
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'Name', 'Extra', 'TeamSlug', 'Email', 'Notes', 'UserId', 'TeamSlug',
        'CreatedBy', 'ModifiedBy', 'DeletedBy'
    ];

    protected $casts = [
        'Extra' => 'array',
    ];


    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserId', 'Id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 't_TeamUser', 'TeamId', 'UserId', $this->primaryKey, 'Id')
            ->withTimestamps('CreatedOn', 'ModifiedOn')->withPivot(['CreatedBy', 'ModifiedBy']);
    }

}
