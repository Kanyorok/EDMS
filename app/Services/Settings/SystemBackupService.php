<?php

namespace App\Services\Settings;

use App\Models\Settings\SystemBackupRun;
use App\Models\Settings\SystemBackupSetting;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class SystemBackupService
{
    public function currentSetting(): SystemBackupSetting
    {
        return SystemBackupSetting::query()->orderBy('Id')->first()
            ?? new SystemBackupSetting([
                'BackupPath' => '',
                'FileNamePrefix' => 'BRERP',
                'ScheduleEnabled' => false,
                'ScheduleTime' => '02:00',
            ]);
    }

    public function saveSettings(array $data, ?int $actorId = null): SystemBackupSetting
    {
        $setting = SystemBackupSetting::query()->orderBy('Id')->first() ?? new SystemBackupSetting();
        $isNew = ! $setting->exists;

        $setting->fill([
            'BackupPath' => trim((string) $data['BackupPath']),
            'FileNamePrefix' => $this->cleanPrefix($data['FileNamePrefix'] ?? null),
            'ScheduleEnabled' => (bool) ($data['ScheduleEnabled'] ?? false),
            'ScheduleTime' => ($data['ScheduleEnabled'] ?? false) ? ($data['ScheduleTime'] ?? null) : null,
            'ModifiedBy' => $actorId,
        ]);

        if ($isNew) {
            $setting->CreatedBy = $actorId;
        }

        $setting->save();

        return $setting;
    }

    public function runBackup(SystemBackupSetting $setting, string $type = 'manual', ?int $actorId = null): SystemBackupRun
    {
        if (! $setting->exists || trim((string) $setting->BackupPath) === '') {
            throw new RuntimeException('Set the database backup path before running a backup.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $databaseName = $connection->getDatabaseName();
        $backupFile = $this->buildBackupFilePath($setting, $databaseName);

        $run = SystemBackupRun::create([
            'BackupSettingID' => $setting->Id,
            'BackupType' => $type,
            'Status' => 'running',
            'DatabaseName' => $databaseName,
            'BackupPath' => $setting->BackupPath,
            'BackupFile' => $backupFile,
            'StartedOn' => now(),
            'RequestedBy' => $actorId,
            'CreatedOn' => now(),
        ]);

        try {
            if ($driver !== 'sqlsrv') {
                throw new RuntimeException("Database backups from ERP are currently supported for SQL Server connections only. Current driver: {$driver}.");
            }

            $connection->statement($this->sqlServerBackupStatement($databaseName, $backupFile));

            $run->update([
                'Status' => 'succeeded',
                'Message' => 'Backup completed successfully.',
                'FinishedOn' => now(),
            ]);

            $setting->forceFill([
                'LastRunAt' => now(),
                'ModifiedBy' => $actorId,
            ])->save();
        } catch (Throwable $e) {
            $run->update([
                'Status' => 'failed',
                'Message' => $e->getMessage(),
                'FinishedOn' => now(),
            ]);

            throw $e;
        }

        return $run->refresh();
    }

    public function runDueScheduledBackups(): int
    {
        $processed = 0;

        SystemBackupSetting::query()
            ->where('ScheduleEnabled', true)
            ->whereNotNull('ScheduleTime')
            ->get()
            ->each(function (SystemBackupSetting $setting) use (&$processed) {
                if (! $this->isDue($setting)) {
                    return;
                }

                $this->runBackup($setting, 'scheduled');
                $processed++;
            });

        return $processed;
    }

    public function isDue(SystemBackupSetting $setting): bool
    {
        if (! $setting->ScheduleEnabled || ! $setting->ScheduleTime) {
            return false;
        }

        $scheduleTime = is_string($setting->ScheduleTime)
            ? $setting->ScheduleTime
            : (string) $setting->ScheduleTime;

        $scheduledAt = now()->startOfDay()->addSeconds($this->secondsFromTime($scheduleTime));

        if (now()->lt($scheduledAt)) {
            return false;
        }

        if ($setting->LastRunAt && $setting->LastRunAt->gte($scheduledAt)) {
            return false;
        }

        return ! SystemBackupRun::query()
            ->where('BackupSettingID', $setting->Id)
            ->where('BackupType', 'scheduled')
            ->where('StartedOn', '>=', $scheduledAt)
            ->exists();
    }

    private function buildBackupFilePath(SystemBackupSetting $setting, string $databaseName): string
    {
        $directory = rtrim(str_replace('/', '\\', trim((string) $setting->BackupPath)), '\\');
        $prefix = $this->cleanPrefix($setting->FileNamePrefix) ?: 'BRERP';
        $db = preg_replace('/[^A-Za-z0-9_-]+/', '_', $databaseName);
        $timestamp = now()->format('Ymd_His');

        return $directory . '\\' . $prefix . '_' . $db . '_' . $timestamp . '.bak';
    }

    private function cleanPrefix(?string $prefix): ?string
    {
        $prefix = trim((string) $prefix);
        if ($prefix === '') {
            return null;
        }

        return preg_replace('/[^A-Za-z0-9_-]+/', '_', $prefix);
    }

    private function sqlServerBackupStatement(string $databaseName, string $backupFile): string
    {
        $database = '[' . str_replace(']', ']]', $databaseName) . ']';
        $disk = str_replace("'", "''", $backupFile);

        return "BACKUP DATABASE {$database} TO DISK = N'{$disk}' WITH INIT, STATS = 10";
    }

    private function secondsFromTime(string $time): int
    {
        [$hours, $minutes, $seconds] = array_pad(explode(':', $time), 3, 0);

        return ((int) $hours * 3600) + ((int) $minutes * 60) + (int) $seconds;
    }
}
