<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class UserNotification extends Model
{
    protected $table = 't_UserNotifications';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'UserID',
        'Type',
        'Title',
        'Body',
        'Source',
        'SourceID',
        'ReadOn',
        'CreatedOn',
    ];
}
