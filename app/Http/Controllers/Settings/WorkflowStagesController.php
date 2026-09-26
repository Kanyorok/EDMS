<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WorkFlowStageRequest;
use App\Models\Auth\Role;
use App\Models\Core\Approval\Permission;
use App\Services\WorkFlowStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class WorkflowStagesController extends Controller
{
    protected $stageService;

    public function __construct(WorkFlowStageService $stageService)
    {
        $this->stageService = $stageService;
    }

    public function store(WorkFlowStageRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $this->authorize('update', \App\Models\Settings\WorkFlow::findOrFail($validated['WorkFlowId']));

            Log::info('Storing new workflow stage', [
                'workflow_id' => $validated['WorkFlowId'],
                'stage_name' => $validated['StageName'],
                'is_final' => ! empty($validated['IsFinalStage']),
            ]);

            Log::info('Storing new workflow stage', [
                'workflow_id' => $validated['WorkFlowId'],
                'stage_name' => $validated['StageName'],
                'is_final' => ! empty($validated['IsFinalStage']),
            ]);

            // Create the stage
            $result = $this->stageService->createStage($validated);

            // Get the stage with relationships - force fresh query
            $stage = \App\Models\Core\Approval\WorkflowStage::with(['type_name', 'workflow', 'permission.roles', 'overridePermission.roles'])
                ->find($result['stage']->Id);

            if (! $stage) {
                throw new \Exception('Stage created but could not be retrieved');
            }

            $permission = $result['permission'] ?? null;

            // Get workflow to check final stage status
            $workflow = \App\Models\Settings\WorkFlow::find($validated['WorkFlowId']);
            $workflow->refresh(); // Force refresh from database

            $isFinalStage = ($stage->StageName === $workflow->FinalStage);

            // Prepare response data with all necessary relationships
            $stageData = [
                'Id' => $stage->Id,
                'StageName' => $stage->StageName,
                'Order' => $stage->Order,
                'EscalationLimit' => $stage->EscalationLimit,
                'WorkFlowId' => $stage->WorkFlowId,
                'IsFinalStage' => $isFinalStage,
                'MaxAmount' => $stage->MaxAmount ?? null,
                'Count' => $stage->Count ?? null,
                'type' => $stage->type_name ? [
                    'TypeID' => $stage->type_name->TypeID,
                    'Name' => $stage->type_name->Name,
                ] : null,
                'role_name' => $permission && $permission->roles ?
                    $permission->roles->pluck('name')->implode(', ') : '-',
                'base_permission' => $stage->overridePermission ? [
                    'id' => $stage->overridePermission->id,
                    'name' => $stage->overridePermission->name,
                ] : null,
                'stage_permission' => $stage->permission ? [
                    'id' => $stage->permission->id,
                    'name' => $stage->permission->name,
                ] : null,
            ];

            Log::info('Workflow stage created successfully', [
                'stage_id' => $stage->Id,
                'is_final' => $isFinalStage,
                'workflow_has_final' => ! empty($workflow->FinalStage),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow stage created successfully'
                    . ($isFinalStage ? ' (final stage)' : ''),
                'stage' => $stageData,
                'workflow_has_final_stage' => ! empty($workflow->FinalStage),
            ]);
        } catch (\App\Exceptions\ErroredException $e) {
            Log::error('Failed to create workflow stage', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Unexpected error in workflow stage creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function disable(string $id): JsonResponse
    {
        try {
            $stage = \App\Models\Core\Approval\WorkflowStage::findOrFail($id);
            $this->authorize('update', \App\Models\Settings\WorkFlow::findOrFail($stage->WorkFlowId));

            Log::info('Attempting to disable workflow stage', ['stage_id' => $id]);

            $workflowId = $stage->WorkFlowId;
            $stageName = $stage->StageName;

            $result = $this->stageService->disableStage((int) $id);

            $workflow = \App\Models\Settings\WorkFlow::find($workflowId);
            $workflow->refresh();

            Log::info('Stage disabled successfully', [
                'stage_id' => $id,
                'stage_name' => $stageName,
                'was_final' => $result['was_final_stage'],
                'new_final' => $result['final_stage'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow stage disabled successfully.',
                'final_stage' => $result['final_stage'],
                'was_final_stage' => $result['was_final_stage'],
                'workflow_has_final_stage' => ! empty($workflow->FinalStage),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to disable workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to disable stage: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function enable(string $id): JsonResponse
    {
        try {
            $stage = \App\Models\Core\Approval\WorkflowStage::findOrFail($id);
            $this->authorize('update', \App\Models\Settings\WorkFlow::findOrFail($stage->WorkFlowId));

            $this->stageService->enableStage((int) $id);

            Log::info('Stage enabled successfully', ['stage_id' => $id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow stage enabled successfully.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to enable workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enable stage: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(WorkFlowStageRequest $request, string $id): JsonResponse
    {
        try {
            $stage = \App\Models\Core\Approval\WorkflowStage::findOrFail($id);
            $this->authorize('update', \App\Models\Settings\WorkFlow::findOrFail($stage->WorkFlowId));

            $validated = $request->validated();
            $stage = $this->stageService->updateStage((int) $id, $validated);

            // Reload with relationships
            $stage = \App\Models\Core\Approval\WorkflowStage::with(['type_name', 'workflow', 'permission.roles', 'overridePermission.roles'])
                ->find($id);

            return response()->json([
                'status' => 'success',
                'message' => 'Workflow stage updated successfully.',
                'stage' => $stage,
            ]);
        } catch (\App\Exceptions\ErroredException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update workflow stage', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stage.',
            ], 500);
        }
    }

    public function syncRoles(Request $request, string $id): JsonResponse
    {
        try {
            $stage = \App\Models\Core\Approval\WorkflowStage::with('workflow')->findOrFail($id);
            $this->authorize('update', \App\Models\Settings\WorkFlow::findOrFail($stage->WorkFlowId));

            $validated = $request->validate([
                'role_ids' => ['nullable', 'array'],
                'role_ids.*' => ['integer', 'exists:t_Roles,id'],
            ]);

            $stagePermission = $stage->permission;
            if (! $stagePermission) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This stage does not have a stage permission.',
                ], 422);
            }

            $candidateRoleIds = $this->eligibleStageRoleIds($stage)->all();
            $selectedRoleIds = collect($validated['role_ids'] ?? [])
                ->map(fn ($roleId) => (int) $roleId)
                ->unique()
                ->values();

            $invalidRoleIds = $selectedRoleIds->diff($candidateRoleIds);
            if ($invalidRoleIds->isNotEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'One or more selected roles do not have the workflow approval permission.',
                ], 422);
            }

            DB::transaction(function () use ($stagePermission, $selectedRoleIds) {
                $actorId = Auth::id();
                $stagePermission->roles()->syncWithPivotValues(
                    $selectedRoleIds->all(),
                    ['CreatedBy' => $actorId, 'ModifiedBy' => $actorId],
                    true
                );
            });

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $roles = Role::query()
                ->whereIn('id', $selectedRoleIds)
                ->orderBy('name')
                ->pluck('name')
                ->values();

            return response()->json([
                'status' => 'success',
                'message' => 'Stage roles updated successfully.',
                'roles' => $roles,
                'role_name' => $roles->isNotEmpty() ? $roles->implode(', ') : '-',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to sync workflow stage roles', [
                'stage_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update stage roles: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function eligibleStageRoleIds(\App\Models\Core\Approval\WorkflowStage $stage): \Illuminate\Support\Collection
    {
        $workflowSource = $stage->workflow?->Source;
        $moduleId = DB::table('t_ModuleSources')
            ->where('DocumentType', $workflowSource)
            ->value('ModuleID');

        $sourceBase = Str::lower(Str::singular(preg_replace('/^t_/i', '', (string) $workflowSource)));
        $sourceApprovalNames = [
            $sourceBase . '-approval',
            $sourceBase . '-approve',
        ];
        $configured = config('workflow.approval_permissions', []);
        $configuredApprovalNames = collect($configured[$workflowSource] ?? $configured[Str::lower((string) $workflowSource)] ?? []);

        $stageApprovalPermissionIds = collect([$stage->OverridePermissionId])
            ->filter()
            ->map(fn ($permissionId) => (int) $permissionId);

        $approvalPermissionIds = Permission::query()
            ->whereIn(DB::raw('LOWER(name)'), collect($sourceApprovalNames)
                ->merge($configuredApprovalNames)
                ->map(fn (string $name) => Str::lower($name))
                ->unique()
                ->all())
            ->pluck('id')
            ->merge($stageApprovalPermissionIds)
            ->filter()
            ->map(fn ($permissionId) => (int) $permissionId)
            ->unique()
            ->values();

        if ($approvalPermissionIds->isEmpty()) {
            $approvalPermissionIds = Permission::query()
                ->when($moduleId, fn ($query) => $query->where('ModuleId', $moduleId))
                ->where('name', 'not like', 'workflowstage_%')
                ->where(function ($query) {
                    $query->where('name', 'like', '%approval%')
                        ->orWhere('name', 'like', '%approve%');
                })
                ->pluck('id');
        }

        if ($approvalPermissionIds->isEmpty()) {
            return collect();
        }

        return Role::query()
            ->whereHas('permissions', fn ($query) => $query->whereIn('t_Permissions.id', $approvalPermissionIds))
            ->pluck('id')
            ->map(fn ($roleId) => (int) $roleId)
            ->values();
    }
}
