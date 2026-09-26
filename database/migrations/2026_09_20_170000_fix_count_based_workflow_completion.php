<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('sps/9. Workflows/[p_ProcessWorkflowAction].sql'));

        if ($sql === false) {
            throw new RuntimeException('Unable to read workflow SQL script: [p_ProcessWorkflowAction].sql');
        }

        // GO is a client batch separator, not valid T-SQL sent through PDO.
        $sql = preg_replace('/^\s*GO\s*$/im', '', $sql);

        DB::unprepared($sql);
    }

    public function down(): void
    {
        // Stored procedure fixes are forward-only. Restoring the former procedure
        // would reintroduce the incorrect CNT-to-ALL threshold conversion.
    }
};
