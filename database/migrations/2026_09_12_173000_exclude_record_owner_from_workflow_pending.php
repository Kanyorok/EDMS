<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * p_ProcessWorkflowPending only excluded whoever clicked "submit for
     * approval" (via t_WorkFlowHistory) from the eligible-approver list - but
     * anyone with access can submit someone else's draft record, so the
     * actual record creator was not reliably excluded from being sent their
     * own approval request. Redeploys the corrected procedure, which now
     * also looks up the source record's own CreatedBy column directly (best
     * effort, via dynamic SQL guarded by a column-existence check) and
     * excludes that user too, regardless of who submitted it.
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
