<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('sps/9. Workflows/[p_ProcessWorkflowAction].sql'));
        if ($sql === false) {
            throw new RuntimeException('Unable to read p_ProcessWorkflowAction.sql.');
        }

        foreach (preg_split('/^\s*GO\s*$/mi', $sql) ?: [] as $batch) {
            if (trim($batch) !== '') {
                DB::unprepared($batch);
            }
        }
    }

    public function down(): void
    {
        // Shared workflow procedures remain installed for older application nodes.
    }
};
