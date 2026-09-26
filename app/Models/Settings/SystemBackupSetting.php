<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SystemBackupSetting extends Model
{
    public const CREATED_AT = 'CreatedOn';
    public const UPDATED_AT = 'ModifiedOn';

    protected $table = 't_SystemBackupSettings';
    protected $primaryKey = 'Id';

    protected $fillable = [
        'BackupPath',
        'FileNamePrefix',
        'ScheduleEnabled',
        'ScheduleTime',
        'LastRunAt',
        'CreatedBy',
        'ModifiedBy',
    ];

    protected $casts = [
        'ScheduleEnabled' => 'boolean',
        'LastRunAt' => 'datetime',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(SystemBackupRun::class, 'BackupSettingID', 'Id');
    }
}
