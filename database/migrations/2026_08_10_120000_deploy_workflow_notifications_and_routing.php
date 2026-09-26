<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['[p_ProcessWorkflowPending].sql', '[p_ProcessWorkflowAction].sql'] as $script) {
            $sql = file_get_contents(database_path('sps/9. Workflows/'.$script));

            if ($sql === false) {
                throw new RuntimeException("Unable to read workflow SQL script: {$script}");
            }

            foreach (preg_split('/^\s*GO\s*$/mi', $sql) ?: [] as $batch) {
                if (trim($batch) !== '') {
                    DB::unprepared($batch);
                }
            }
        }

        // Keep the historical batch entry point safe for external schedulers
        // after its hardened dependency has been deployed.
        DB::unprepared('
            CREATE OR ALTER PROCEDURE dbo.p_ProcessWorkflowStages
            AS
            BEGIN
                SET NOCOUNT ON;
                EXEC dbo.p_ProcessWorkflowPending;
            END
        ');
    }

    public function down(): void
    {
        // Shared workflow procedures remain installed for older application nodes.
    }
};
