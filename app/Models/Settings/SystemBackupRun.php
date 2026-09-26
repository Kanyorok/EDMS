<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemBackupRun extends Model
{
    public $timestamps = false;

    protected $table = 't_SystemBackupRuns';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'BackupSettingID',
        'BackupType',
        'Status',
        'DatabaseName',
        'BackupPath',
        'BackupFile',
        'Message',
        'StartedOn',
        'FinishedOn',
        'RequestedBy',
        'CreatedOn',
    ];

    protected $casts = [
        'StartedOn' => 'datetime',
        'FinishedOn' => 'datetime',
    ];

    public function setting(): BelongsTo
    {
        return $this->belongsTo(SystemBackupSetting::class, 'BackupSettingID', 'Id');
    }
}
