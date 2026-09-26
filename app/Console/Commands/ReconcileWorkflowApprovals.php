<?php

namespace App\Console\Commands;

use App\Services\Core\WorkflowReconciliationService;
use Illuminate\Console\Command;

class ReconcileWorkflowApprovals extends Command
{
    protected $signature = 'workflow:reconcile';

    protected $description = 'Retry pending-approval creation for workflow submissions stuck with no eligible approvers, now that role/permission assignments may have changed';

    public function handle(WorkflowReconciliationService $service): int
    {
        $result = $service->reconcileStuckApprovals();

        $this->info(sprintf(
            'Checked %d in-flight submission(s): healed %d, still stuck %d.',
            $result['checked'],
            $result['healed'],
            $result['stillStuck']
        ));

        return self::SUCCESS;
    }
}
