<?php

namespace App\Console\Commands;

use App\Services\Settings\SystemBackupService;
use Illuminate\Console\Command;
use Throwable;

class RunDueSystemBackups extends Command
{
    protected $signature = 'system-backups:run-due';

    protected $description = 'Run system database backups whose configured daily schedule is due.';

    public function handle(SystemBackupService $backupService): int
    {
        try {
            $processed = $backupService->runDueScheduledBackups();
            $this->info("Processed {$processed} due system backup(s).");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
