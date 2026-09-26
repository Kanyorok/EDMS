<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $script = database_path('sps/9. Workflows/[f_getUserWithPermission].sql');
        $sql = file_get_contents($script);

        if ($sql === false) {
            throw new RuntimeException("Unable to read workflow SQL script: {$script}");
        }

        foreach (preg_split('/^\s*GO\s*$/mi', $sql) ?: [] as $batch) {
            if (trim($batch) !== '') {
                DB::unprepared($batch);
            }
        }
    }

    public function down(): void
    {
        // This function is a shared workflow runtime dependency and is retained
        // during rollback so deployed approval procedures remain operational.
    }
};
