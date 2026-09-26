<?php

namespace App\Models\Communication;

use App\Enums\EmailPriorityEnum;
use App\Enums\EmailStatusEnum;
use App\Enums\EmailTypeEnum;
use App\Enums\Core\EmailChannelEnum;
use App\Models\DMS\Image;
use App\Traits\Model\ImageTrait;
use App\Traits\Model\UserActorTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Email extends Model
{
    use ImageTrait;
    use UserActorTrait;
    use SoftDeletes;

    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';
    public const DELETED_AT = 'DeletedOn';

    protected $table = 't_Emails';
    protected $primaryKey = 'EmailID';

    public static function getPrimaryKey(): string
    {
        return 'EmailID';
    }
    protected $fillable = [
        'MailID', 'Channel', 'Type', 'Status', 'Priority', 'From', 'To', 'CC', 'BCC', 'Subject', 'Body', 'Text', 'Party', 'PartyID',
        'Extra', 'Source', 'SourceID', 'Dated',
        'CreatedBy', 'ModifiedBy', 'DeletedBy',
    ];


    protected $casts = [
        'Extra' => 'object',
        'To' => 'array',
        'CC' => 'array',
        'BCC' => 'array',
        'Dated' => 'datetime',
        'Priority' => EmailPriorityEnum::class,
        'Status' => EmailStatusEnum::class,
        'Type' => EmailTypeEnum::class,
        'Channel' => EmailChannelEnum::class,
    ];

    public function getRouteKeyName(): string
    {
        return 'EmailID';
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 't_EmailImage', 'EmailId', 'ImageId', 'EmailID', 'ImageID')
            ->withTimestamps('CreatedOn', 'ModifiedOn');
    }

    public function party(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, "Party", "PartyID");
    }

    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, "Source", "SourceID");
    }

    protected function getImageName(): string
    {
        return $this->Subject;
    }
}
