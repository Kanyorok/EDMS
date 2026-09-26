<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * p_ProcessWorkflowPending's maker-checker exclusion picked the CreatedBy of
     * the OLDEST t_WorkFlowHistory row for a record (ORDER BY CreatedOn ASC),
     * instead of the current submission cycle's actual submitter. Once a record
     * has been through more than one submit/approve/reject cycle, that stale
     * lookup stops excluding the real submitter, letting them be added as an
     * eligible approver of their own request. Redeploys the corrected
     * procedure, which scopes the maker lookup to the latest "Submitted for
     * Approval" history entry.
     */
    public function up(): void
    {
        $sql = file_get_contents(database_path('sps/9. Workflows/[p_ProcessWorkflowPending].sql'));

        if ($sql === false) {
            throw new RuntimeException('Unable to read workflow SQL script: [p_ProcessWorkflowPending].sql');
        }

        foreach (preg_split('/^\s*GO\s*$/mi', $sql) ?: [] as $batch) {
            if (trim($batch) !== '') {
                DB::unprepared($batch);
            }
        }
    }

    public function down(): void
    {
        // No-op: this migration only redeploys a corrected procedure body.
    }
};
