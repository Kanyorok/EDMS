<?php

namespace App\Services;

use App\DTOs\WorkflowStageResult;
use App\Exceptions\ErroredException;
use App\Models\Core\Approval\Permission;
use App\Models\Core\Approval\WorkflowStage;
use App\Models\Settings\WorkFlow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkFlowStageService
{
    /**
     * CREATE WORKFLOW STAGE
     */
    public function createStage(array $data)
    {
        $user = Auth::user();
        if (! $user) {
            throw new ErroredException('User not authenticated.');
        }

        try {
            $workflow = WorkFlow::findOrFail($data['WorkFlowId']);

            // Check if workflow already has a final stage
            if (! empty($workflow->FinalStage)) {
                throw new ErroredException('This workflow already has a final stage. Please remove the final stage designation before adding more stages.');
            }

            $nextOrder = WorkflowStage::where('WorkFlowId', $workflow->Id)->max('Order') + 1;

            $moduleId = DB::table('t_ModuleSources')
                ->where('DocumentType', $workflow->Source)
                ->value('ModuleID');

            if (! $moduleId) {
                throw new ErroredException('Module not found for workflow source.');
            }



            $results = DB::select('EXEC p_AddWorkflowStage2 
                @Order = ?, 
                @StageName = ?, 
                @EscalationLimit = ?, 
                @WorkflowID = ?, 
                @WorkflowTypeID = ?, 
                @Count = ?, 
                @StatusID = ?, 
                @CreatedBy = ?, 
                @ModuleID = ?', [
                $nextOrder,
                $data['StageName'],
                $data['EscalationLimit'],
                $data['WorkFlowId'],
                $data['WorkFlowTypeId'],
                $data['Count'] ?? null,
                $data['StatusId'] ?? null,
                $user->Id,
                $moduleId,
            ]);



            // Fix for SP leaving transaction open
            $this->fixTransactionCount();

            Log::info('p_AddWorkflowStage2 Result', ['result' => $results]);

            if (empty($results)) {
                throw new ErroredException('Stored procedure returned no results.');
            }

            $dto = WorkflowStageResult::fromDatabaseResult($results[0]);

            if ($dto->isError()) {
                throw new ErroredException($dto->message);
            }

            // Clear all caches before fetching the stage
            $this->clearAllCaches($workflow->Id);

            // Wait a moment for DB to settle
            usleep(100000); // 100ms

            // Fetch the newly created stage with fresh query
            $stageModel = DB::table('t_WorkflowStages')
                ->where('Id', $dto->newStageId)
                ->first();

            if (! $stageModel) {
                // Debug: Check if it exists via raw DB
                $rawStage = DB::table('t_WorkflowStages')->where('Id', $dto->newStageId)->first();


                if ($rawStage) {
                    // If found via raw DB but not Eloquent, it's a model issue.
                    // Try to hydrate manually or investigate model scopes.
                    Log::warning('Stage found via raw DB but not Eloquent. Possible scope or casting issue.');
                    $stageModel = new WorkflowStage((array)$rawStage);
                    $stageModel->exists = true;
                } else {
                    throw new ErroredException('Stage creation failed - Record not found after SP execution.');
                }
            }

            // Clear permission cache to ensure the new permission (created by SP) is visible to Spatie
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            // Approval routing must stay stage-specific. The SP creates
            // workflowstage_<StageName> and stores it on the stage; replacing
            // it with a broad module permission like requisition-approval makes
            // every role with that permission eligible for every stage.
            $permission = Permission::find($dto->permissionId);

            if (! $permission) {
                $permissionName = 'workflowstage_' . str_replace(' ', '', $data['StageName']);
                $permission = Permission::where('name', $permissionName)->first();

                if (! $permission) {
                    $permission = Permission::create([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                        'ModuleId' => $moduleId,
                    ]);
                }

                DB::table('t_WorkflowStages')
                    ->where('Id', $stageModel->Id)
                    ->update(['PermissionId' => $permission->id]);
                $stageModel->PermissionId = $permission->id;
            }

            $selectedPermissionId = (int) ($data['PermissionId'] ?? 0);
            if ($selectedPermissionId <= 0 || ! Permission::query()->whereKey($selectedPermissionId)->exists()) {
                throw new ErroredException('Select a valid workflow approval permission for this stage.');
            }

            DB::table('t_WorkflowStages')
                ->where('Id', $stageModel->Id)
                ->update(['OverridePermissionId' => $selectedPermissionId]);
            $stageModel->OverridePermissionId = $selectedPermissionId;

            // Stage approver roles are selected explicitly from the workflow
            // details screen. Do not auto-assign the generated stage permission
            // to the creator/admin roles, otherwise every stage inherits the
            // same approver set.

            // Update FinalStage if marked as final
            if (! empty($data['IsFinalStage'])) {
                DB::table('t_Workflows')
                    ->where('Id', $workflow->Id)
                    ->update([
                        'FinalStage' => $stageModel->StageName,
                        'ModifiedBy' => $user->Id,
                        'ModifiedOn' => now(),
                    ]);

                Log::info('Set final stage', [
                    'workflow_id' => $workflow->Id,
                    'final_stage' => $stageModel->StageName,
                ]);
            } else {
                // Update modified timestamp
                DB::table('t_Workflows')
                    ->where('Id', $workflow->Id)
                    ->update([
                        'ModifiedBy' => $user->Id,
                        'ModifiedOn' => now(),
                    ]);
            }

            // Clear caches again after all updates
            $this->clearAllCaches($workflow->Id);

            // Reload the stage with relationships
            $stageModel = WorkflowStage::with(['type_name', 'workflow', 'permission.roles', 'overridePermission.roles'])
                ->find($stageModel->Id);

            Log::info('Workflow stage created successfully', [
                'stage_id' => $stageModel->Id,
                'stage_name' => $stageModel->StageName,
            ]);

            return [
                'dto' => $dto,
                'stage' => $stageModel,
                'permission' => $permission,
            ];
        } catch (ErroredException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Workflow stage creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ErroredException('Error creating workflow stage: ' . $e->getMessage());
        }
    }

    /**
     * DISABLE WORKFLOW STAGE
     *
     * Stages are never hard-deleted: t_WorkFlowHistory/t_WorkFlowPending rows
     * reference a stage by Id, and p_ProcessWorkflowPending resolves that Id
     * via `WHERE Id = @StageID AND DeletedOn IS NULL`. Hard-deleting the row
     * (or soft-deleting it via DeletedOn) breaks that lookup for any pending
     * or historical approval still pointing at the stage. IsActive is a
     * separate flag: a disabled stage stays resolvable by Id (DeletedOn stays
     * NULL) but is excluded from selection as the current/next stage for new
     * or advancing workflow items.
     */
    public function disableStage(int $id): array
    {
        try {
            $stage = WorkflowStage::findOrFail($id);
            $workflow = WorkFlow::findOrFail($stage->WorkFlowId);

            $stageName = $stage->StageName;
            $workflowId = $workflow->Id;
            $wasFinalStage = ($stage->StageName === $workflow->FinalStage);

            Log::info('Disabling workflow stage', [
                'stage_id' => $id,
                'stage_name' => $stageName,
                'was_final' => $wasFinalStage,
                'workflow_id' => $workflowId,
            ]);

            DB::table('t_WorkflowStages')
                ->where('Id', $id)
                ->update([
                    'IsActive' => false,
                    'ModifiedBy' => Auth::id(),
                    'ModifiedOn' => now(),
                ]);

            // If the disabled stage was the final stage, clear the designation
            // so the workflow doesn't silently keep routing to a disabled stage.
            // Let the user explicitly mark another stage as final.
            $newFinalStage = $wasFinalStage ? null : $workflow->FinalStage;

            DB::table('t_Workflows')
                ->where('Id', $workflowId)
                ->update([
                    'FinalStage' => $newFinalStage,
                    'ModifiedBy' => Auth::id(),
                    'ModifiedOn' => now(),
                ]);

            $this->clearAllCaches($workflowId);

            activity()
                ->performedOn($stage)
                ->event('update')
                ->log("Disabled workflow stage: {$stageName}");

            Log::info('Stage disabled successfully', [
                'stage_id' => $id,
                'new_final_stage' => $newFinalStage,
            ]);

            return [
                'success' => true,
                'final_stage' => $newFinalStage,
                'was_final_stage' => $wasFinalStage,
            ];
        } catch (\Throwable $e) {
            Log::error('Failed to disable workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * RE-ENABLE WORKFLOW STAGE
     */
    public function enableStage(int $id): array
    {
        try {
            $stage = WorkflowStage::findOrFail($id);

            DB::table('t_WorkflowStages')
                ->where('Id', $id)
                ->update([
                    'IsActive' => true,
                    'ModifiedBy' => Auth::id(),
                    'ModifiedOn' => now(),
                ]);

            $this->clearAllCaches($stage->WorkFlowId);

            activity()
                ->performedOn($stage)
                ->event('update')
                ->log("Enabled workflow stage: {$stage->StageName}");

            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('Failed to enable workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * UPDATE STAGE
     *
     * The "Approval Permission" field on the stage form (`PermissionId` in
     * the request) maps to the OverridePermissionId column, mirroring
     * createStage(): the PermissionId column itself is reserved for the
     * auto-generated workflowstage_<name> permission and must not be
     * overwritten by the selected approval permission.
     */
    public function updateStage(int $id, array $data): WorkFlowStage
    {
        try {
            $stage = WorkflowStage::findOrFail($id);
            $workflow = WorkFlow::findOrFail($stage->WorkFlowId);

            $isFinalStage = ! empty($data['IsFinalStage']);
            unset($data['IsFinalStage']);

            $columns = [
                'StageName' => $data['StageName'] ?? $stage->StageName,
                'EscalationLimit' => $data['EscalationLimit'] ?? $stage->EscalationLimit,
                'WorkFlowTypeId' => $data['WorkFlowTypeId'] ?? $stage->WorkFlowTypeId,
                'Count' => $data['Count'] ?? $stage->Count,
                'ModifiedBy' => Auth::id(),
                'ModifiedOn' => now(),
            ];

            if (array_key_exists('PermissionId', $data)) {
                $columns['OverridePermissionId'] = $data['PermissionId'];
            }

            DB::table('t_WorkflowStages')
                ->where('Id', $id)
                ->update($columns);

            $wasFinalStage = ($stage->StageName === $workflow->FinalStage);
            $newStageName = $columns['StageName'];

            if ($isFinalStage) {
                DB::table('t_Workflows')
                    ->where('Id', $workflow->Id)
                    ->update([
                        'FinalStage' => $newStageName,
                        'ModifiedBy' => Auth::id(),
                        'ModifiedOn' => now(),
                    ]);
            } elseif ($wasFinalStage) {
                // Stage was final but is no longer marked as such: clear the designation.
                DB::table('t_Workflows')
                    ->where('Id', $workflow->Id)
                    ->update([
                        'FinalStage' => null,
                        'ModifiedBy' => Auth::id(),
                        'ModifiedOn' => now(),
                    ]);
            }

            // Clear caches
            $this->clearAllCaches($stage->WorkFlowId);

            activity()
                ->performedOn($stage)
                ->event('update')
                ->log("Updated workflow stage: {$newStageName}");

            return WorkflowStage::find($id);
        } catch (\Throwable $e) {
            Log::error('Failed to update workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ErroredException('Failed to update workflow stage: ' . $e->getMessage());
        }
    }

    /**
     * GET FINAL STAGE (highest Order)
     */
    public function getFinalStage(int $workflowId): ?WorkFlowStage
    {
        return WorkflowStage::where('WorkFlowId', $workflowId)
            ->orderBy('Order', 'desc')
            ->first();
    }

    private function fixTransactionCount(): void
    {
        try {
            $dbTranCount = DB::select('SELECT @@TRANCOUNT as count')[0]->count;
            $laravelTranCount = DB::transactionLevel();

            while ($dbTranCount > $laravelTranCount) {
                DB::unprepared('COMMIT TRANSACTION');
                $dbTranCount = DB::select('SELECT @@TRANCOUNT as count')[0]->count;
                Log::warning('Fixed mismatched transaction count from SP (Forced COMMIT)');
            }
        } catch (\Throwable $e) {
            Log::warning('Could not check/fix transaction count: ' . $e->getMessage());
        }
    }

    /**
     * Clear workflow caches
     */
    private function clearAllCaches(int $workflowId): void
    {
        Cache::forget("workflow_{$workflowId}");
        Cache::forget("workflow_stages_{$workflowId}");
    }
}
