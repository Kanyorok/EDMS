<?php

namespace App\Services\Core;

use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Models\Core\Approval\Workflow;
use App\Models\Core\Approval\WorkflowHistory;
use App\Models\Core\Approval\WorkflowPending;
use App\Models\Core\Approval\WorkflowStage;
use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

abstract class ApprovalWorkflowService
{
    /**
     * Get CodeDetail by enum and CodeID
     */
    public static function codeDetail(BackedEnum $status, string $CodeID): CodeDetail
    {
        $code = CodeDetail::query()
            ->where('CodeID', $CodeID)
            ->where('Value', (string) $status->value)
            ->first();

        if ($code instanceof CodeDetail) {
            return $code;
        }

        Log::error("CodeDetail not found", [
            'CodeID' => $CodeID,
            'Value' => $status->value,
        ]);

        throw new ErroredException('Invalid Status');
    }

    /**
     * Get the first stage of a workflow for a given table
     */
    protected function getPermissionFromStage(string $table): ?WorkflowStage
    {


        $workflow = Workflow::where('Source', $table)
            ->whereNull('DeletedOn')
            ->first();

        if (! $workflow) {
            Log::error("No workflow found", ['table' => $table]);

            return null;
        }



        $stage = WorkflowStage::where('WorkFlowId', $workflow->Id)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->orderBy('Order', 'asc')
            ->first();

        if (! $stage) {
            Log::error("No valid stage found", [
                'WorkFlowId' => $workflow->Id,
                'Table' => $table,
            ]);

            return null;
        }

        if (! $stage->PermissionId) {
            Log::error("CRITICAL: Stage found but PermissionId is NULL", [
                'WorkFlowId' => $workflow->Id,
                'StageId' => $stage->Id,
                'StageName' => $stage->StageName,
                'Table' => $table,
            ]);

            return null;
        }



        return $stage;
    }

    /**
     * Get the current active stage id for a given table + sourceId
     */
    protected function getCurrentStageId(string $table, string|int $sourceId): ?int
    {
        $pendingStage = DB::table('t_WorkFlowPending')
            ->where('Source', $table)
            ->where('SourceID', (string)$sourceId)
            ->whereNull('DeletedOn')
            ->orderBy('CreatedOn', 'desc')
            ->value('Stage');

        if ($pendingStage && is_numeric($pendingStage)) {
            return (int)$pendingStage;
        }

        $history = WorkflowHistory::query()
            ->where('Source', $table)
            ->where('SourceID', (string)$sourceId)
            ->whereNull('DeletedOn')
            ->orderBy('CreatedOn', 'desc')
            ->first();

        if ($history && ! empty($history->Stage)) {
            return is_numeric($history->Stage) ? (int)$history->Stage : null;
        }

        $firstStage = $this->getPermissionFromStage($table);
        if ($firstStage) {
            return (int)$firstStage->Id;
        }

        Log::warning("No current stage found", [
            'table' => $table,
            'sourceId' => $sourceId,
        ]);

        return null;
    }

    /**
     * Get the status column name for a given morph alias/table
     */
    private function getStatusColumnForTable(string $morphAlias, string $table): string
    {
        // Check configuration first
        $columnMappings = config('workflow.status_columns', []);

        if (isset($columnMappings[$morphAlias])) {
            return $columnMappings[$morphAlias];
        }

        // Check if table has ApprovalStatus column
        $hasApprovalStatus = DB::getSchemaBuilder()
            ->hasColumn($table, 'ApprovalStatus');

        if ($hasApprovalStatus) {
            return 'ApprovalStatus';
        }

        // Default to Status

        return 'Status';
    }

    /**
     * Create a workflow history entry with forced commit.
     * Uses raw SQL to bypass Eloquent transaction context, ensuring the history
     * persists even if subsequent operations (e.g., SP call) fail or rollback.
     */
    protected function createHistoryEntry(
        string $table,
        string|int $sourceId,
        int $statusId,
        int $stageId,
        int $actorId,
        string $notes = null,
        float $amount = null
    ): WorkflowHistory {
        try {
            $now = now();

            // Use raw insert to bypass Eloquent's transaction tracking.
            // This ensures the history is committed immediately.
            $inserted = DB::insert(
                'INSERT INTO t_WorkFlowHistory 
                (Source, SourceID, StatusId, Stage, Amount, Notes, isApproved, CreatedBy, ModifiedBy, CreatedOn, ModifiedOn) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $table,
                    (string)$sourceId,
                    $statusId,
                    (string)$stageId,
                    $amount,
                    $notes,
                    null, // isApproved
                    $actorId,
                    $actorId,
                    $now,
                    $now,
                ]
            );

            if (!$inserted) {
                Log::error("Failed to insert WorkflowHistory via raw SQL", [
                    'table' => $table,
                    'sourceId' => $sourceId,
                    'stageId' => $stageId,
                ]);

                throw new Exception("Failed to create workflow history entry");
            }

            // Fetch the created entry to return it (for compatibility)
            $entry = WorkflowHistory::where('Source', $table)
                ->where('SourceID', (string)$sourceId)
                ->where('CreatedOn', $now)
                ->latest('Id')
                ->first();

            return $entry ?? new WorkflowHistory([
                'Source' => $table,
                'SourceID' => (string)$sourceId,
                'StatusId' => $statusId,
                'Stage' => (string)$stageId,
                'Amount' => $amount,
                'Notes' => $notes,
                'isApproved' => null,
                'CreatedBy' => $actorId,
                'ModifiedBy' => $actorId,
                'CreatedOn' => $now,
                'ModifiedOn' => $now,
            ]);
        } catch (\Throwable $e) {
            Log::error("Exception creating WorkflowHistory entry", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Execute workflow action (approve/reject) via stored procedure
     */
    private function _executeWorkflowAction(
        User $actor,
        CodeDetail $status,
        string $source,
        string|int $sourceId,
        string $remarks,
        string $statusColumn
    ): array {
        $class = Relation::getMorphedModel($source);
        if (! ($class && class_exists($class))) {
            throw new ErroredException('Invalid Related Entity');
        }

        $statusValueToSet = $this->getStatusValueForModule($source, $status);
        $table = (new $class())->getTable();



        // Get current stage ID BEFORE calling SP
        $currentStageId = $this->getCurrentStageId($table, $sourceId);


        //  Check state BEFORE calling SP
        $this->logWorkflowState($table, $sourceId, 'BEFORE_ACTION');

        try {
            // NOTE: No DB::beginTransaction() here — the stored procedures manage their
            // own transactions with SET XACT_ABORT ON. Wrapping SP calls in PHP transactions
            // creates nested savepoints (trans2) that SQL Server destroys on XACT_ABORT,
            // causing "Cannot roll back trans2" errors.

            DB::statement("SET NOCOUNT OFF");

            $result = DB::select(
                'EXEC p_ProcessWorkflowAction 
                @Source = ?, 
                @SourceID = ?, 
                @UserID = ?, 
                @UserName = ?, 
                @Notes = ?, 
                @StatusColumn = ?, 
                @StatusID = ?, 
                @StatusValueToSet = ?',
                [
                    $table,
                    (string)$sourceId,
                    $actor->Id,
                    $actor->Name ?? $actor->UserID,
                    $remarks,
                    $statusColumn,
                    $status->ID,
                    $statusValueToSet,
                ]
            );



            if (! empty($result) && isset($result[0]->Status)) {
                if ($result[0]->Status === 'ERROR') {
                    Log::error("p_ProcessWorkflowAction returned ERROR", [
                        'message' => $result[0]->Message ?? 'Unknown error',
                    ]);

                    throw new ErroredException($result[0]->Message ?? 'Workflow action failed');
                }
            }

            //  Check state AFTER calling SP
            $this->logWorkflowState($table, $sourceId, 'AFTER_ACTION');

            $result = [
                'success' => true,
                'message' => $result[0]->Message ?? 'Action recorded successfully',
                'workflowStatus' => $result[0]->WorkflowStatus ?? 'Unknown',
                'stageCompleted' => $result[0]->StageCompleted ?? false,
            ];

            //  Use the properly defined $currentStageId
            // Only advance to next stage for APPROVALS, not rejections.
            // The SP's Section 12 already handles rejection completely:
            // it updates the source table status, cleans up pending records,
            // and returns. Calling advanceToNextStage after rejection would
            // overwrite the rejected status with an approved status.
            $isRejection = isset($result['workflowStatus'])
                && stripos($result['workflowStatus'], 'reject') !== false;

            if ($result['stageCompleted'] && ! $isRejection) {
                // Advance to next stage or finalize approval if no next stage exists
                $this->advanceToNextStage($table, $sourceId, $currentStageId, $actor->Id, $statusColumn);
            }

            return $result;
        } catch (ErroredException $e) {
            Log::error("ErroredException in _executeWorkflowAction", [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error in _executeWorkflowAction', [
                'error' => $e->getMessage(),
                'table' => $table,
                'sourceId' => $sourceId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ErroredException($this->_extractSqlServerError($e->getMessage()));
        }
    }

    /**
     * Log comprehensive workflow state for diagnostics
     */
    private function logWorkflowState(string $table, string|int $sourceId, string $stage): void
    {
        try {
            // Get approved status ID
            $approvedStatusId = DB::table('t_CodeDetails')
                ->where('Description', 'Approved')
                ->value('ID');

            // Count pending entries
            $pendingCount = DB::table('t_WorkFlowPending')
                ->where('Source', $table)
                ->where('SourceID', (string)$sourceId)
                ->whereNull('DeletedOn')
                ->count();

            // Get current stage from pending
            $currentStage = DB::table('t_WorkFlowPending')
                ->where('Source', $table)
                ->where('SourceID', (string)$sourceId)
                ->whereNull('DeletedOn')
                ->value('Stage');

            // Count approvals for current stage
            $approvalCount = 0;
            if ($currentStage) {
                $approvalCount = DB::table('t_WorkFlowHistory')
                    ->where('Source', $table)
                    ->where('SourceID', (string)$sourceId)
                    ->where('Stage', $currentStage)
                    ->where('StatusId', $approvedStatusId)
                    ->whereNull('DeletedOn')
                    ->distinct('CreatedBy')
                    ->count('CreatedBy');
            }

            // Get stage requirements
            $stageRequirements = null;
            if ($currentStage) {
                $stageRequirements = DB::table('t_WorkFlowStages')
                    ->where('Id', $currentStage)
                    ->select('Count', 'StageName', 'PermissionId')
                    ->first();
            }


            // List all pending users
            $pendingUsers = DB::table('t_WorkFlowPending as p')
                ->join('t_Users as u', 'p.UserId', '=', 'u.Id')
                ->where('p.Source', $table)
                ->where('p.SourceID', (string)$sourceId)
                ->whereNull('p.DeletedOn')
                ->select('u.Id', 'u.Name', 'p.Stage')
                ->get();



            // List all approvals
            $approvals = DB::table('t_WorkFlowHistory as h')
                ->join('t_Users as u', 'h.CreatedBy', '=', 'u.Id')
                ->where('h.Source', $table)
                ->where('h.SourceID', (string)$sourceId)
                ->where('h.StatusId', $approvedStatusId)
                ->whereNull('h.DeletedOn')
                ->select('u.Id', 'u.Name', 'h.Stage', 'h.CreatedOn')
                ->orderBy('h.CreatedOn')
                ->get();
        } catch (\Throwable $e) {
            Log::error("Failed to log workflow state", [
                'error' => $e->getMessage(),
                'stage' => $stage,
            ]);
        }
    }

    /**
     * Approve action
     */
    protected function approveAction(
        User $actor,
        CodeDetail $status,
        string $source,
        string|int $sourceId,
        string $remarks,
        string $statusColumn = 'Status'
    ): bool {
        $result = $this->_executeWorkflowAction($actor, $status, $source, $sourceId, $remarks, $statusColumn);



        return $result['success'] ?? false;
    }

    /**
     * Reject action
     */
    protected function rejectAction(
        User $actor,
        CodeDetail $status,
        string $source,
        string|int $sourceId,
        string $remarks,
        string $statusColumn = 'Status'
    ): bool {
        $result = $this->_executeWorkflowAction($actor, $status, $source, $sourceId, $remarks, $statusColumn);

        return $result['success'] ?? false;
    }

    /**
     * Determine the status value to set based on module
     */
    private function getStatusValueForModule(string $source, CodeDetail $status): string
    {
        $mappings = config('workflow', []);

        if (isset($mappings[$source]) && is_array($mappings[$source])) {
            $moduleMapping = $mappings[$source];

            if (isset($moduleMapping[$status->Description])) {
                return $moduleMapping[$status->Description];
            }

            $aliases = ['Approval' => 'Approved'];
            $normalizedDescription = $aliases[$status->Description] ?? $status->Description;
            if (isset($moduleMapping[$normalizedDescription])) {
                return $moduleMapping[$normalizedDescription];
            }
        }

        // Safe fallback: use the configured CodeDetail value for the workflow code.
        // Descriptions like "Approved" can overflow compact status columns (e.g. CHAR(1)).
        if (! empty($status->Value)) {
            return (string) $status->Value;
        }

        return (string) $status->Description;
    }

    /**
     * Extract clean error message from SQL Server
     */
    private function _extractSqlServerError(string $errorMessage): string
    {
        if (preg_match('/\[SQL Server\]\s*(.+?)(?:\s*\[|$)/s', $errorMessage, $matches)) {
            $errorMessage = trim($matches[1]);
        } elseif (preg_match('/SQLSTATE\[.*?\]:\s*(.+?)(?:\s*\(|$)/s', $errorMessage, $matches)) {
            $errorMessage = trim($matches[1]);
        }

        $cleanMessage = preg_replace('/\(Connection:.*?\)/', '', $errorMessage);
        $cleanMessage = preg_replace('/SQLSTATE\[.*?\]:\s*/', '', $cleanMessage);

        return trim($cleanMessage) ?: 'Database operation failed';
    }

    /**
     * Submit a record for approval workflow
     */
    protected function submittedAction(
        User $actor,
        CodeDetail $status,
        $model,
        string $source,
        string|int $sourceId,
        string $remarks,
        ?float $amount = null,
        ?array $specificUserIds = null,
        ?int $branchId = null,
        int|array|null $permissionId = null
    ): bool {
        $class = Relation::getMorphedModel($source);
        if (! ($class && class_exists($class))) {
            Log::error("Invalid morph alias", ['source' => $source]);

            throw new ErroredException('Invalid Related Entity');
        }

        $table = $model->getTable();
        $sourceId = $sourceId ?? $model->getKey();



        try {

            $hasActivePending = WorkflowPending::query()
                ->where('Source', $table)
                ->where('SourceID', (string) $sourceId)
                ->whereNull('DeletedOn')
                ->exists();

            if ($hasActivePending) {
                Log::warning("Already submitted (active pending exists)", ['table' => $table, 'sourceId' => $sourceId]);

                throw new ErroredException("This item has already been submitted for approval");
            }

            // Get first stage
            $stage = $this->getPermissionFromStage($table);
            if (! $stage || ! $stage->PermissionId) {
                $this->handleWorkflowError(
                    "Workflow Configuration is missing,Please Contact Administrator.",
                    "No workflow Configuration found",
                    ['table' => $table, 'sourceId' => $sourceId]
                );
            }

            $effectiveAmount = $amount ?? ($model->Amount ?? null);
            $specificUserIdsParam = $this->formatWorkflowSpecificUserIds($specificUserIds);
            $permissionIdParam = $this->formatWorkflowPermissionIdOverride($permissionId);

            // **CRITICAL**: Create and commit workflow history BEFORE any other operation.
            // This ensures history persists even if the SP call or subsequent operations fail.
            // We do this by saving the current transaction level, committing, creating history,
            // and letting the caller resume transactions if needed.
            
            $transactionLevel = DB::transactionLevel();

            // If we're in a transaction, commit it (so history commits independently)
            while (DB::transactionLevel() > 0) {
                DB::commit();
            }

            // Now create history (will auto-commit since we're outside any transaction)
            $this->createHistoryEntry(
                $table,
                $sourceId,
                $status->ID,
                (int)$stage->Id,
                $actor->Id,
                $remarks,
                $effectiveAmount
            );

            // Re-start transaction if we exited one (for the rest of the workflow)
            if ($transactionLevel > 0) {
                for ($i = 0; $i < $transactionLevel; $i++) {
                    DB::beginTransaction();
                }
            }

            // Call stored procedure (inside new transaction if we started one).
            // @SubmittedStatusIdOverride is the exact CodeDetail id this module used to
            // record its own "Submitted" state (resolved dynamically above via
            // self::codeDetail() against the module's own CodeID group) - passed through
            // so the SP marks this history row as routed without having to guess a
            // module-agnostic status id via a hardcoded Description lookup.
            $result = DB::select(
                'EXEC p_ProcessWorkflowPending @Source = ?, @SourceID = ?, @StageID = ?, @Amount = ?, @SpecificUserIds = ?, @BranchId = ?, @PermissionIdOverride = ?, @SubmittedStatusIdOverride = ?',
                [
                    $table,
                    (string)$sourceId,
                    (int)$stage->Id,
                    $effectiveAmount,
                    $specificUserIdsParam,
                    $branchId,
                    $permissionIdParam,
                    $status->ID,
                ]
            );



            // Check for errors returned by SP
            if (! empty($result) && isset($result[0]->Status)) {
                if ($result[0]->Status === 'ERROR') {
                    $message = $result[0]->Message ?? 'Unknown error';

                    $permissionId = null;
                    if (preg_match('/PermissionId:\s*(\d+)/i', $message, $m)) {
                        $permissionId = (int)$m[1];
                    }

                    $permissionName = null;
                    $eligibleCount = null;
                    if ($permissionId) {
                        try {
                            $permissionName = DB::table('t_Permissions')->where('id', $permissionId)->value('name');
                            $cntRow = DB::selectOne('SELECT COUNT(*) AS cnt FROM dbo.f_getUserWithPermission(?)', [$permissionId]);
                            $eligibleCount = $cntRow?->cnt ?? null;
                        } catch (\Throwable $e) {
                            // ignore enrichment errors
                        }
                    }

                    Log::error("p_ProcessWorkflowPending returned ERROR", [
                        'message' => $message,
                        'permission_id' => $permissionId,
                        'permission_name' => $permissionName,
                        'eligible_users' => $eligibleCount,
                    ]);

                    if ($permissionId) {
                        $suffix = $permissionName ? " (Permission: {$permissionName})" : '';

                        throw new ErroredException($message . $suffix . '. Assign this permission to at least one approver (not the maker) and retry.');
                    }

                    throw new ErroredException($message ?: 'Failed to create pending approvals');
                }
            }

            // Log state after creating pending approvals
            $this->logWorkflowState($table, $sourceId, 'AFTER_SUBMISSION');


            return true;
        } catch (ErroredException $e) {
            Log::error("ErroredException in submittedAction", [
                'message' => $e->getMessage(),
                'table' => $table,
                'sourceId' => $sourceId,
            ]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error in submittedAction', [
                'error' => $e->getMessage(),
                'table' => $table,
                'sourceId' => $sourceId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ErroredException($this->_extractSqlServerError($e->getMessage()));
        }
    }

    //error handling method
    private function handleWorkflowError(
        string  $userMessage,
        string  $logMessage,
        array $context = []
    ): void {
        Log::error($logMessage, $context);

        throw new ErroredException($userMessage);
    }

    private function formatWorkflowSpecificUserIds(?array $specificUserIds): ?string
    {
        if (empty($specificUserIds)) {
            return null;
        }

        $ids = collect($specificUserIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        return $ids->isEmpty() ? null : $ids->implode(',');
    }

    private function formatWorkflowPermissionIdOverride(int|array|null $permissionId): int|string|null
    {
        if (is_null($permissionId)) {
            return null;
        }

        if (is_int($permissionId)) {
            return $permissionId > 0 ? $permissionId : null;
        }

        $ids = collect($permissionId)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        return $ids->isEmpty() ? null : $ids->implode(',');
    }

    /**
     * Get workflow history
     */
    protected function historyData(string $source, int $limit = 1000): Collection
    {
        $class = Relation::getMorphedModel($source);
        if ($class && class_exists($class)) {
            $table = (new $class())->getTable();
        } else {
            throw new ErroredException('Invalid Related Entity');
        }

        return WorkflowHistory::query()
            ->where('Source', $table)
            ->with(['creator', 'status'])
            ->orderBy('CreatedOn', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Check if user can approve
     */
    public function canApprove(string $source, string|int $sourceId, User $user): bool
    {
        $sources = $this->resolveWorkflowSources($source);
        if (empty($sources)) {
            return false;
        }

        $table = $this->resolveWorkflowTable($source);
        if (! $table) {
            return false;
        }

        $pendingRows = WorkflowPending::whereIn('Source', $sources)
            ->where('SourceID', (string)$sourceId)
            ->where('UserId', $user->Id)
            ->whereNull('DeletedOn')
            ->get(['Stage']);

        if ($pendingRows->isEmpty()) {
            return false;
        }

        $currentStageId = $this->getCurrentStageId($table, $sourceId);
        if (! $currentStageId) {
            $currentStageId = (int) $pendingRows
                ->pluck('Stage')
                ->filter(fn ($stage) => is_numeric($stage))
                ->first();
        }

        if (! $currentStageId) {
            return false;
        }

        $pendingRows = $pendingRows
            ->filter(fn ($row) => (string) $row->Stage === (string) $currentStageId)
            ->values();

        if ($pendingRows->isEmpty()) {
            return false;
        }

        // Maker-checker rule. The maker is whoever submitted the CURRENT pending
        // cycle, not whoever owns the oldest history row for this record - a
        // record's history can span more than one submit/approve/reject cycle
        // (e.g. rejected-and-resubmitted), so "oldest row" goes stale after the
        // first cycle and stops excluding the actual current submitter.
        $submittedStatusId = DB::table('t_CodeDetails')
            ->where('Description', 'Submitted for Approval')
            ->value('Id');

        $maker = WorkflowHistory::whereIn('Source', $sources)
            ->where('SourceID', (string)$sourceId)
            ->whereNull('DeletedOn')
            ->when($submittedStatusId, fn ($q) => $q->where('StatusId', $submittedStatusId))
            ->orderBy('CreatedOn', 'desc')
            ->first();

        if ($maker && (int)$maker->CreatedBy === (int)$user->Id) {
            return false;
        }

        // The submitter above can be a different user than whoever actually
        // created the record (anyone can submit someone else's draft) - the
        // record's own owner must be excluded too, regardless of who clicked
        // "submit for approval" on their behalf.
        try {
            if (Schema::hasColumn($table, 'CreatedBy')) {
                $documentOwnerId = DB::table($table)->where('Id', $sourceId)->value('CreatedBy');
                if ($documentOwnerId && (int) $documentOwnerId === (int) $user->Id) {
                    return false;
                }
            }
        } catch (\Throwable $e) {
            // Best-effort: don't fail the approval check if the source table
            // doesn't follow the Id/CreatedBy convention.
        }

        $permissionTable = config('permission.table_names.permissions', 't_Permissions');
        $stageIds = $pendingRows->pluck('Stage')->filter()->unique();

        foreach ($stageIds as $stageId) {
            $stage = WorkflowStage::query()
                ->select('PermissionId', 'StageName')
                ->where('Id', (int)$stageId)
                ->first();

            if (! $stage) {
                Log::warning('Workflow stage not found during approval check', [
                    'stage_id' => $stageId,
                    'source' => $source,
                    'source_id' => $sourceId,
                ]);

                return false;
            }

            $permissionNames = [];
            if (! empty($stage->PermissionId)) {
                $configuredPermissionName = DB::table($permissionTable)
                    ->where('id', (int)$stage->PermissionId)
                    ->value('name');

                if ($configuredPermissionName) {
                    $permissionNames[] = $configuredPermissionName;
                }
            }

            $candidate = 'workflowstage_' . str_replace(' ', '', (string)$stage->StageName);
            $exists = DB::table($permissionTable)->where('name', $candidate)->exists();
            if ($exists) {
                $permissionNames[] = $candidate;
            }

            $permissionNames = array_values(array_unique(array_filter($permissionNames)));

            if (empty($permissionNames)) {
                Log::warning('Workflow stage permission not configured', [
                    'stage_id' => $stageId,
                    'stage_name' => $stage->StageName,
                    'source' => $source,
                    'source_id' => $sourceId,
                ]);

                return false;
            }

            $hasMatchingPermission = false;
            foreach ($permissionNames as $permissionName) {
                if ($user->hasPermissionTo($permissionName)) {
                    $hasMatchingPermission = true;

                    break;
                }
            }

            if (! $hasMatchingPermission) {
                Log::warning('Approval Check Mismatch: User is in pending table but Spatie hasPermissionTo returned false. Allowing based on DB engine state.', [
                    'user_id' => $user->Id,
                    'stage_id' => $stageId,
                    'permissions_checked' => $permissionNames,
                ]);
                // We do NOT return false here because p_ProcessWorkflowPending
                // already explicitly verified the user's role/permission via SQL Server
                // and inserted them into t_WorkFlowPending.
                // Spatie often fails here due to guard_name mismatches (e.g. sanctum vs web).
            }
        }

        return true;
    }

    private function resolveWorkflowSources(string $source): array
    {
        $sources = [$source];

        $class = Relation::getMorphedModel($source);
        if (! $class && class_exists($source)) {
            $class = $source;
        }

        if ($class && class_exists($class)) {
            try {
                $instance = new $class();
                $sources[] = $instance->getTable();
                $sources[] = $instance->getMorphClass();
            } catch (\Throwable $e) {
                Log::warning('Unable to resolve workflow sources', [
                    'source' => $source,
                    'error' => $e->getMessage(),
                ]);
            }

            $alias = array_search($class, Relation::morphMap(), true);
            if ($alias) {
                $sources[] = $alias;
            }
        }

        return array_values(array_unique(array_filter($sources, function ($value) {
            return is_string($value) && $value !== '';
        })));
    }

    private function resolveWorkflowTable(string $source): ?string
    {
        $class = Relation::getMorphedModel($source);
        if (! $class && class_exists($source)) {
            $class = $source;
        }

        if (! ($class && class_exists($class))) {
            return null;
        }

        try {
            $instance = new $class();

            return $instance->getTable();
        } catch (\Throwable $e) {
            Log::warning('Unable to resolve workflow table', [
                'source' => $source,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get workflow status for a record
     */
    public function getWorkflowStatus(string $source, string|int $sourceId): array
    {
        $class = Relation::getMorphedModel($source);
        if (! ($class && class_exists($class))) {
            throw new ErroredException('Invalid Related Entity');
        }

        $table = (new $class())->getTable();
        $currentStageId = $this->getCurrentStageId($table, $sourceId);

        if (! $currentStageId) {
            return [
                'hasWorkflow' => false,
                'currentStage' => null,
                'pendingApprovers' => [],
                'completedApprovals' => [],
            ];
        }

        $stage = WorkflowStage::find($currentStageId);

        if (! $stage) {
            return [
                'hasWorkflow' => false,
                'currentStage' => null,
                'pendingApprovers' => [],
                'completedApprovals' => [],
            ];
        }

        $pendingApprovers = DB::table('t_WorkFlowPending as p')
            ->join('t_Users as u', 'p.UserId', '=', 'u.Id')
            ->where('p.Source', $table)
            ->where('p.SourceID', (string)$sourceId)
            ->where('p.Stage', (string)$currentStageId)
            ->whereNull('p.DeletedOn')
            ->whereNull('u.DeletedOn')
            ->select('u.Id', 'u.Name', 'u.Email')
            ->get()
            ->toArray();

        $approvedStatusId = DB::table('t_CodeDetails')
            ->whereIn('Description', ['Approved', 'Rejected'])
            ->pluck('ID');

        $completedApprovals = DB::table('t_WorkFlowHistory as h')
            ->join('t_Users as u', 'h.CreatedBy', '=', 'u.Id')
            ->where('h.Source', $table)
            ->where('h.SourceID', (string)$sourceId)
            ->where('h.Stage', (string)$currentStageId)
            ->whereIn('h.StatusId', $approvedStatusId)
            ->whereNull('h.DeletedOn')
            ->select('u.Id', 'u.Name', 'u.Email', 'h.CreatedOn', 'h.Notes')
            ->orderBy('h.CreatedOn', 'desc')
            ->get()
            ->toArray();

        return [
            'hasWorkflow' => true,
            'currentStage' => [
                'id' => $stage->Id,
                'name' => $stage->StageName,
                'order' => $stage->Order,
            ],
            'pendingApprovers' => $pendingApprovers,
            'completedApprovals' => $completedApprovals,
            'totalPending' => count($pendingApprovers),
            'totalCompleted' => count($completedApprovals),
        ];
    }

    /**
     * Check if there are more stages after the current one
     */
    private function hasNextStage(string $table, int $currentStageId): bool
    {
        $currentStage = WorkflowStage::find($currentStageId);
        if (! $currentStage) {
            return false;
        }

        $nextStage = DB::table('t_WorkFlowStages')
            ->where('WorkFlowId', $currentStage->WorkFlowId)
            ->where('Order', '>', $currentStage->Order)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->orderBy('Order', 'asc')
            ->first();

        return ! is_null($nextStage);
    }

    /**
     * Cancel/withdraw workflow
     */
    public function cancelWorkflow(
        User $actor,
        string $source,
        string|int $sourceId,
        string $reason = 'Cancelled by submitter'
    ): bool {
        $class = Relation::getMorphedModel($source);
        if (! ($class && class_exists($class))) {
            throw new ErroredException('Invalid Related Entity');
        }

        $table = (new $class())->getTable();

        $submitterId = DB::table('t_WorkFlowHistory')
            ->where('Source', $table)
            ->where('SourceID', (string)$sourceId)
            ->whereNull('DeletedOn')
            ->orderBy('CreatedOn', 'asc')
            ->value('CreatedBy');

        if (! $submitterId || $submitterId != $actor->Id) {
            throw new ErroredException("Only the submitter can cancel the workflow");
        }

        try {
            DB::beginTransaction();

            DB::table('t_WorkFlowPending')
                ->where('Source', $table)
                ->where('SourceID', (string)$sourceId)
                ->whereNull('DeletedOn')
                ->update([
                    'DeletedOn' => now(),
                    'DeletedBy' => $actor->Id,
                    'ModifiedOn' => now(),
                    'ModifiedBy' => $actor->Id,
                ]);

            DB::table('t_WorkFlowHistory')
                ->where('Source', $table)
                ->where('SourceID', (string)$sourceId)
                ->whereNull('DeletedOn')
                ->update([
                    'DeletedOn' => now(),
                    'DeletedBy' => $actor->Id,
                    'ModifiedOn' => now(),
                    'ModifiedBy' => $actor->Id,
                ]);



            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error cancelling workflow', ['error' => $e->getMessage()]);

            throw new ErroredException("Failed to cancel workflow");
        }
    }

    //advancing to the next stage
    // NOTE: No DB::beginTransaction() here — the stored procedures manage their own
    // transactions with SET XACT_ABORT ON. Adding PHP-level transactions around SP calls
    // creates nested savepoints that SQL Server destroys, causing "Cannot roll back trans2" errors.
    private function advanceToNextStage(string $table, string|int $sourceId, ?int $currentStageId, int $userId, string $statusColumn = 'Status'): void
    {
        try {
            if (! $currentStageId) {
                Log::warning("Cannot advance: currentStageId is null", ['table' => $table, 'sourceId' => $sourceId]);

                return;
            }

            $currentStage = WorkflowStage::query()
                ->where('Id', $currentStageId)
                ->whereNull('DeletedOn')
                ->first();

            if (! $currentStage) {
                Log::warning("Current stage not found", ['stageId' => $currentStageId]);

                return;
            }

            $currentOrder = $currentStage->Order;
            $workflowId = $currentStage->WorkFlowId;

            $nextStage = DB::table('t_WorkFlowStages')
                ->where('WorkFlowId', $workflowId)
                ->where('Order', '>', $currentOrder)
                ->whereNull('DeletedOn')
                ->where('IsActive', true)
                ->orderBy('Order', 'asc')
                ->first();

            if ($nextStage) {
                // Retrieve the amount from the history table (mirrors SP logic)
                $amount = 0;

                try {
                    $historyAmountResult = DB::table('t_WorkFlowHistory')
                        ->where('Source', $table)
                        ->where('SourceID', (string)$sourceId)
                        ->whereNull('DeletedOn')
                        ->whereNotNull('Amount')
                        ->orderBy('CreatedOn', 'desc')
                        ->limit(1)
                        ->value('Amount');

                    $amount = $historyAmountResult ?? 0;
                } catch (\Throwable $e) {
                    Log::warning("Could not retrieve amount from history for next stage", ['error' => $e->getMessage()]);
                }

                // Determine effective permission for next stage based on amount
                $effectivePermissionId = $nextStage->PermissionId;
                $limitType = 'DEFAULT';

                if ($amount > 0) {
                    try {
                        $permissionResult = DB::select("
                        SELECT PermissionId, MaxAmount, LimitType
                        FROM dbo.f_getPermissionForAmount(?, ?)
                    ", [$nextStage->Id, $amount]);

                        if (! empty($permissionResult)) {
                            $effectivePermissionId = $permissionResult[0]->PermissionId;
                            $limitType = $permissionResult[0]->LimitType;
                        }
                    } catch (\Throwable $e) {
                        Log::error("Error determining amount-based permission", ['error' => $e->getMessage()]);
                    }
                }

                // Use the stored procedure to handle pending creation
                // This is more efficient and consistent with submission logic

                try {
                    $spResult = DB::select(
                        'EXEC p_ProcessWorkflowPending @Source = ?, @SourceID = ?, @StageID = ?, @Amount = ?, @PermissionIdOverride = ?',
                        [
                            $table,
                            (string)$sourceId,
                            (int)$nextStage->Id,
                            $amount,
                            $nextStage->OverridePermissionId ?? null,
                        ]
                    );



                    if (! empty($spResult) && isset($spResult[0]->Status)) {
                        if ($spResult[0]->Status === 'ERROR') {
                            Log::error("p_ProcessWorkflowPending returned ERROR during advancement", [
                                'message' => $spResult[0]->Message ?? 'Unknown error',
                            ]);

                            throw new ErroredException($spResult[0]->Message ?? 'Failed to create pending approvals for next stage');
                        }
                    }

                    // SP already sends email notifications to next-stage approvers
                    // No need for duplicate notification from PHP side
                } catch (ErroredException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    Log::error("Error calling p_ProcessWorkflowPending for next stage", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    throw new ErroredException("Failed to advance to next stage: " . $e->getMessage());
                }
            } else {
                // No next stage - workflow fully approved


                try {
                    // Get the morph alias from table name
                    $morphAlias = array_search($table, array_map(function ($class) {
                        return (new $class())->getTable();
                    }, Relation::morphMap()));

                    if (! $morphAlias) {
                        // Fallback: try to determine from table name
                        $morphAlias = Str::snake(Str::singular(str_replace('t_', '', $table)));
                    }

                    // Get the approved status value for this module
                    $approvedStatusValue = $this->getFinalApprovedStatus($morphAlias, $table);

                    // Dynamically get the primary key column from the model
                    $primaryKeyColumn = 'Id';  // Default fallback

                    try {
                        $modelClass = Relation::getMorphedModel($morphAlias);
                        if ($modelClass && class_exists($modelClass)) {
                            $primaryKeyColumn = (new $modelClass())->getKeyName();  // E.g., 'id', 'PlanID', 'custom_id'
                        }
                    } catch (\Throwable $e) {
                        Log::warning("Could not determine primary key for table {$table}, using default 'Id'", [
                            'error' => $e->getMessage(),
                            'morphAlias' => $morphAlias,
                        ]);
                    }



                    // Update the status column in the source table
                    DB::statement("
            UPDATE {$table}
            SET {$statusColumn} = ?,
                ModifiedBy = ?,
                ModifiedOn = GETDATE()
            WHERE {$primaryKeyColumn} = ?
        ", [$approvedStatusValue, $userId, $sourceId]);
                } catch (\Throwable $e) {
                    Log::error("Failed to update source table status", [
                        'error' => $e->getMessage(),
                        'table' => $table,
                        'sourceId' => $sourceId,
                    ]);
                    // Don't throw - workflow is complete, this is just a status update issue
                }
            }
        } catch (\Throwable $e) {
            Log::error("Failed to advance stage", [
                'error' => $e->getMessage(),
                'table' => $table,
                'sourceId' => $sourceId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     *  Notify next-stage approvers via email
     */
    private function notifyNextStageApprovers(Collection $users, object $nextStage, string $table, string|int $sourceId): void
    {
        // Get system user ID (similar to SP)
        $systemUserId = DB::table('t_Users')
            ->where('UserID', 'ERPSYS')
            ->whereNull('DeletedOn')
            ->value('Id');

        if (! $systemUserId) {
            Log::error("System user ERPSYS not found for notifications");

            return;
        }

        foreach ($users as $user) {
            try {
                // Customize the message as needed
                $subject = 'New Workflow Approval Pending';
                $message = "Dear {$user->Name},\n\n" .
                    "A new approval is pending for your review.\n" .
                    "Stage: {$nextStage->StageName}\n" .
                    "Source: {$table}, ID: {$sourceId}\n\n" .
                    "Please log in to review and approve.\n\n" .
                    "Best regards,\nWorkflow System";

                // Call your email SP
                DB::statement("
                    EXEC p_sendNotificationEmail
                        @UserID = :userId,
                        @Subject = :subject,
                        @Message = :message,
                        @SenderId = :senderId,
                        @Source = :source,
                        @SourceID = :sourceId
                ", [
                    'userId' => $user->Id,
                    'subject' => $subject,
                    'message' => $message,
                    'senderId' => $systemUserId,
                    'source' => $table,
                    'sourceId' => (string)$sourceId,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed to notify approver via SP", [
                    'userId' => $user->Id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * NEW: Dynamically determine the final approved status for a module
     */
    private function getFinalApprovedStatus(string $workflowSource, string $table): string
    {
        try {
            // Check workflow configuration or module mapping
            $mappings = config('workflow', []);

            // Try to find the mapping for this workflow source
            if (isset($mappings[$workflowSource]) && isset($mappings[$workflowSource]['Approved'])) {
                $approvedValue = $mappings[$workflowSource]['Approved'];

                // If it's an enum, get its value
                if ($approvedValue instanceof \BackedEnum) {
                    return $approvedValue->value;
                }

                return (string) $approvedValue;
            }

            // Prefer CodeDetails lookup using the module CodeID when available.
            $codeDetailsApprovedValue = $this->getApprovedStatusFromCodeDetails($workflowSource, $table);
            if ($codeDetailsApprovedValue !== null) {
                return $codeDetailsApprovedValue;
            }

            // Look for common status patterns in the table's code details
            $commonApprovedStatuses = ['a', 'approved', 'complete', 'completed', 'done', 'final'];

            foreach ($commonApprovedStatuses as $status) {
                $exists = DB::table('t_CodeDetails')
                    ->where('CodeID', 'LIKE', '%' . $workflowSource . '%')
                    ->where('Value', $status)
                    ->where('Description', 'LIKE', '%Approved%')
                    ->exists();

                if ($exists) {
                    return $status;
                }
            }

            //  Get from the table's typical status values
            $tableStatus = DB::table('t_CodeDetails')
                ->where('CodeID', 'LIKE', '%' . $workflowSource . '%')
                ->where('Description', 'LIKE', '%Approved%')
                ->value('Value');

            if ($tableStatus) {
                return $tableStatus;
            }

            //  Fallback - analyze the table structure and common values
            $fallbackStatus = $this->determineFallbackStatus($table);

            Log::warning("Using fallback approved status", [
                'table' => $table,
                'workflowSource' => $workflowSource,
                'fallbackStatus' => $fallbackStatus,
            ]);

            return $fallbackStatus;
        } catch (\Throwable $e) {
            Log::error("Error determining final approved status", [
                'workflowSource' => $workflowSource,
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            // Ultimate fallback
            return 'a';
        }
    }

    /**
     * Resolve final approved status directly from t_CodeDetails for known workflow sources.
     */
    private function getApprovedStatusFromCodeDetails(string $workflowSource, string $table): ?string
    {
        $codeId = $this->resolveStatusCodeId($workflowSource, $table);
        if (! $codeId) {
            return null;
        }

        $value = DB::table('t_CodeDetails')
            ->where('CodeID', $codeId)
            ->whereIn('Description', ['Approved', 'Approval'])
            ->whereNull('DeletedOn')
            ->orderByRaw("CASE WHEN Description = 'Approved' THEN 0 ELSE 1 END")
            ->value('Value');

        return $value ? (string) $value : null;
    }

    /**
     * Resolve status CodeID for modules that store status values in t_CodeDetails.
     */
    private function resolveStatusCodeId(string $workflowSource, string $table): ?string
    {
        $normalizedSource = strtolower(trim($workflowSource));
        $normalizedTable = strtolower(trim($table));

        $codeIdBySource = [
            't_rfq' => 'RequisitionStatus',
            'rfqid' => 'RequisitionStatus',
            'hr_workforce_requirement' => 'RecruitmentRequestApprovalStatus',
            't_hrworkforcerequirements' => 'RecruitmentRequestApprovalStatus',
            'hr_recruitment_plan' => 'RecruitmentPlanApprovalStatus',
            't_hrrecruitmentplans' => 'RecruitmentPlanApprovalStatus',
        ];

        return $codeIdBySource[$normalizedSource]
            ?? $codeIdBySource[$normalizedTable]
            ?? null;
    }

    /**
     *  Determine fallback status by analyzing the table
     */
    private function determineFallbackStatus(string $table): string
    {
        try {
            // Convert table name to morph alias (adjust if needed)
            $morphAlias = Str::snake($table); // e.g., 'department_needs'

            // Load status mapping from config
            $statusMapping = config("workflow.{$morphAlias}", []);

            if (! empty($statusMapping)) {
                // Prefer 'Approved' if defined
                if (isset($statusMapping['Approved'])) {
                    return $statusMapping['Approved'];
                }

                // Otherwise return the first mapped status
                return reset($statusMapping);
            }
        } catch (\Throwable $e) {
            Log::error("Error determining fallback status from config", [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);
        }

        // Ultimate fallback if config is missing
        return 'a';
    }
}
