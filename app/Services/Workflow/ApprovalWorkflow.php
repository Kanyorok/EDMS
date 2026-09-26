<?php

namespace App\Services\Workflow;

use App\Exceptions\ErroredException;
use App\Models\Auth\User; 
use App\Models\Procurement\DepartmentNeed; 
use App\Services\Core\ApprovalWorkflowService; 
use BackedEnum; 
use Illuminate\Database\Eloquent\Collection; 
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log; 

class ApprovalWorkflow extends ApprovalWorkflowService
{
    private string $codeId;
    private string $statusColumn;

    /**
     * Constructor to configure the workflow for a specific module.
     *
     * @param string $codeId The CodeID for the module
     * @param string $statusColumn The dynamic status column to be passed
     */
    public function __construct(string $codeId, string $statusColumn = 'Status')
    {
        $this->codeId = $codeId;
        $this->statusColumn = $statusColumn;
    }

    /**
     * Submit a model for approval (generic version).
     *
     * @param mixed $model The model instance (e.g., DepartmentNeed, Tender).
     * @param User $actor The user submitting.
     * @param BackedEnum $pendingStatus The pending status enum value.
     * @param string $remarks Optional remarks.
     * @param float|null $amount Optional workflow amount override.
     * @return bool
     * @throws ErroredException
     */
    public function submit(
        $model,
        User $actor,
        BackedEnum $pendingStatus,
        string $remarks = 'Submitted',
        ?float $amount = null,
        ?array $specificUserIds = null,
        ?int $branchId = null,
        int|array|null $permissionId = null
    ): bool {
        try {
            $status = self::codeDetail($pendingStatus, $this->codeId);



            // Use the model's morph alias
            $result = $this->submittedAction(
                $actor,
                $status,
                $model, // model instance
                $model::getPrimaryKey() ?? $model->getMorphClass(), // Source alias
                $model->getKey(), // sourceID
                $remarks,
                $amount,
                $specificUserIds,
                $branchId,
                $permissionId
            );



            return $result;
        } catch (\Exception $e) {
            Log::error("Failed to submit for approval", [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Approve a model
     *
     * @param mixed $model The model instance.
     * @param User $actor The user approving.
     * @param BackedEnum $approvedStatus The approved status enum value.
     * @param string $remarks Optional remarks.
     * @param string|null $statusColumn Optional status column override
     * @return bool
     * @throws ErroredException
     */
    public function approve($model, User $actor, BackedEnum $approvedStatus, string $remarks = 'Approved', ?string $statusColumn = null): bool
    {
        try {
            $status = self::codeDetail($approvedStatus, $this->codeId);

            // Use provided column or fall back to instance default
            $columnToUse = $statusColumn ?? $this->statusColumn;



            // Call approveAction - can return boolean OR array
            $result = $this->approveAction(
                $actor,
                $status,
                $model::getPrimaryKey(),
                $model->getKey(),
                $remarks,
                $columnToUse
            );

            //  Handle both boolean and array returns
            $success = $this->extractSuccessValue($result);



            // Return the boolean
            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to approve", [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Extract success value from either boolean or array result
     *
     * @param bool|array $result The result from approveAction/rejectAction
     * @return bool
     */
    private function extractSuccessValue($result): bool
    {
        if (is_bool($result)) {
            return $result;
        }

        if (is_array($result)) {
            return isset($result['success']) && $result['success'] === true;
        }


        return false;
    }

    /**
     * Reject a model (generic version).
     *
     * @param mixed $model The model instance.
     * @param User $actor The user rejecting.
     * @param BackedEnum $rejectedStatus The rejected status enum value.
     * @param string $remarks Optional remarks.
     * @param string|null $statusColumn The status column name override
     * @return bool
     * @throws ErroredException
     */
    public function reject($model, User $actor, BackedEnum $rejectedStatus, string $remarks = 'Rejected', ?string $statusColumn = null): bool
    {
        try {
            $status = self::codeDetail($rejectedStatus, $this->codeId);

            // Use provided column or fall back to instance default
            $columnToUse = $statusColumn ?? $this->statusColumn;



            $result = $this->rejectAction(
                $actor,
                $status,
                $model::getPrimaryKey(),
                $model->getKey(),
                $remarks,
                $columnToUse
            );

            //  Handle both boolean and array returns
            $success = $this->extractSuccessValue($result);



            return $success;
        } catch (\Exception $e) {
            Log::error("Failed to reject", [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get workflow history for a module.
     *
     * @param string $morphAlias The morph alias for the model.
     * @param int $limit
     * @return Collection
     * @throws ErroredException
     */
    public function history(string $morphAlias, int $limit = 1000): Collection
    {
        return $this->historyData($morphAlias, $limit);
    }

    /**
     * Get workflow history for a specific model instance.
     *
     * @param mixed $model The model instance (must have a workflowHistory() relationship).
     * @return Collection
     */
    public function historyForModel($model): Collection
    {


        return $model->workflowHistory()
            ->with(['creator', 'status', 'stage'])
            ->orderBy('CreatedOn', 'desc')
            ->get();
    }

    /**
     * Check if a user can approve a specific model.
     *
     * @param mixed $model The model instance.
     * @param User $user The user to check.
     * @return bool
     */
    public function canApproveModel($model, User $user): bool 
    { 
        try { 
            $source = method_exists($model, 'getPrimaryKey') ? $model::getPrimaryKey() : (method_exists($model, 'getMorphClass') ? $model->getMorphClass() : get_class($model)); 
            $canApprove = parent::canApprove( 
                $source, 
                $model->getKey(), 
                $user 
            ); 



            return $canApprove; 
        } catch (\Exception $e) { 
            Log::error("Permission check failed", [ 
                'model_class' => get_class($model), 
                'model_id' => $model->getKey(), 
                'user_id' => $user->Id, 
                'error' => $e->getMessage(), 
            ]); 

            return false; 
        } 
    } 

    /** 
     * Ensure pending approvers exist for the current stage of a model. 
     * 
     * This is a safety-net for cases where pending approvals were cleared out-of-band 
     * (e.g., custom state transitions) and an approver needs to re-queue the workflow. 
     */ 
    public function ensurePendingForModel($model, ?float $amount = null): bool 
    { 
        $table = $model->getTable(); 
        $sourceId = $model->getKey(); 

        $hasPending = DB::table('t_WorkFlowPending') 
            ->where('Source', $table) 
            ->where('SourceID', (string) $sourceId) 
            ->whereNull('DeletedOn') 
            ->exists(); 

        if ($hasPending) { 
            return true; 
        } 
 
        $stage = $this->getPermissionFromStage($table); 
        if (! $stage || ! $stage->PermissionId) { 
            return false; 
        } 
 
        DB::statement("SET NOCOUNT OFF"); 
 
        $result = DB::select( 
            'EXEC p_ProcessWorkflowPending @Source = ?, @SourceID = ?, @StageID = ?, @Amount = ?', 
            [ 
                $table, 
                (string) $sourceId, 
                (int) $stage->Id, 
                $amount, 
            ] 
        ); 
 
        if (! empty($result) && isset($result[0]->Status) && $result[0]->Status === 'ERROR') { 
            throw new ErroredException($result[0]->Message ?? 'Failed to create pending approvals'); 
        } 
 
        return true; 
    } 
 
    /** 
     * Rebuild pending approvals for the model's current workflow stage. 
     * 
     * Use this when workflow membership/permissions changed after submission and the approver 
     * is not present in `t_WorkFlowPending` for the current stage. 
     */ 
    public function refreshPendingForCurrentStage($model, ?float $amount = null): bool 
    { 
        $table = $model->getTable(); 
        $sourceId = $model->getKey(); 
 
        $stageId = $this->getCurrentStageId($table, $sourceId); 
        if (! $stageId) { 
            return false; 
        } 
 
        DB::statement("SET NOCOUNT OFF"); 
 
        $result = DB::select( 
            'EXEC p_ProcessWorkflowPending @Source = ?, @SourceID = ?, @StageID = ?, @Amount = ?', 
            [ 
                $table, 
                (string) $sourceId, 
                (int) $stageId, 
                $amount, 
            ] 
        ); 
 
        if (! empty($result) && isset($result[0]->Status) && $result[0]->Status === 'ERROR') { 
            throw new ErroredException($result[0]->Message ?? 'Failed to refresh pending approvals'); 
        } 
 
        return true; 
    } 

    /** 
     * Cancel a workflow 
     */ 
    public function cancel($model, User $actor, string $reason = 'Cancelled'): bool 
    {
        try {
            return $this->cancelWorkflow($actor, $model::getPrimaryKey(), $model->getKey(), $reason);
        } catch (\Exception $e) {
            Log::error("Failed to cancel workflow", [
                'model_class' => get_class($model),
                'model_id' => $model->getKey(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get workflow status
     */
    public function getStatus($model): array
    {
        return $this->getWorkflowStatus($model::getPrimaryKey(), $model->getKey());
    }

    /**
     * Get the status column being used by this workflow instance
     */
    public function getStatusColumn(): string
    {
        return $this->statusColumn;
    }
}
