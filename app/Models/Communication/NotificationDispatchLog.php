<?php

namespace App\Models\Communication;

use Illuminate\Database\Eloquent\Model;

class NotificationDispatchLog extends Model
{
    protected $table = 't_NotificationDispatchLogs';
    protected $primaryKey = 'Id';
    public $timestamps = false;

    protected $fillable = [
        'Channel',
        'Type',
        'Key',
        'CreatedBy',
        'CreatedOn',
    ];
}
