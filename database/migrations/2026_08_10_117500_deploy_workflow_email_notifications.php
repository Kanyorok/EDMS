<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $script = database_path('sps/8. Core/p_sendNotificationEmail.sql');
        $sql = file_get_contents($script);

        if ($sql === false) {
            throw new RuntimeException("Unable to read workflow notification SQL script: {$script}");
        }

        foreach (preg_split('/^\s*GO\s*$/mi', $sql) ?: [] as $batch) {
            if (trim($batch) !== '') {
                DB::unprepared($batch);
            }
        }
    }

    public function down(): void
    {
        // Notification delivery is a shared database dependency and remains
        // installed during rollback for existing workflow procedures.
    }
};
