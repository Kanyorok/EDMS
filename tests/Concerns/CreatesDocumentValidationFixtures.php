<?php

namespace Tests\Concerns;

use App\Enums\Core\RoleEnum;
use App\Models\Auth\User;
use App\Models\Core\Approval\Workflow;
use App\Models\Core\Approval\WorkflowStage;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;
use App\Services\DMS\Verification\ValidationTypeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Fixtures for the document-validation workflow tests.
 *
 * These tests cannot use DatabaseTransactions. ApprovalWorkflowService::submittedAction()
 * deliberately commits every open transaction before writing workflow history
 * ("while (DB::transactionLevel() > 0) DB::commit();"), which destroys the rollback wrapper:
 * history would survive the test while the stored procedure's pending rows disappeared, so
 * assertions read empty and rows leaked into the database. Everything created here is therefore
 * tracked and removed by cleanupDocumentValidationFixtures() in tearDown().
 */
trait CreatesDocumentValidationFixtures
{
    /** @var array<int,int> */
    protected array $createdUserIds = [];

    /** @var array<int,int> */
    protected array $createdValidationIds = [];

    /** @var array<int,int> */
    protected array $createdTypeIds = [];

    /** @var array<int,int> */
    protected array $createdWorkflowIds = [];

    /** @var array<int,int> */
    protected array $createdPermissionIds = [];

    /**
     * t_Users has unique indexes on UserID, Email and Phone, so every fixture user needs its
     * own value for all three - Phone included, even though it is only decorative here.
     */
    protected function makeUser(string $prefix = 'TU'): User
    {
        $id = $prefix . Str::upper(Str::random(8));

        $user = User::create([
            'UserID' => $id,
            'Name' => 'Test ' . $id,
            'Email' => Str::lower($id) . '@example.test',
            'Phone' => '9' . random_int(100000000, 999999999) . Str::upper(Str::random(4)),
            'Linked' => false,
            'Notes' => 'created by DocumentValidationWorkflowTest',
            'Password' => 'test-account',
            'IsActive' => true,
            'CreatedBy' => 1,
            'ModifiedBy' => 1,
        ]);

        $this->createdUserIds[] = (int) $user->Id;

        return $user;
    }

    protected function makeValidationType(User $actor, string $name = null): DocumentValidationType
    {
        $type = ValidationTypeService::create($name ?? 'Test Type ' . Str::random(6), $actor)->type;
        $this->createdTypeIds[] = (int) $type->Id;

        return $type;
    }

    /**
     * A workflow bound to t_DocumentValidations with one active stage per approver, in order.
     *
     * @param  array<int,User>  $approversByStage  approver for stage 1, stage 2, ...
     */
    protected function makeValidationWorkflow(array $approversByStage, User $actor): Workflow
    {
        $workflow = Workflow::create([
            'Name' => 'Test Validation Workflow ' . Str::random(6),
            'Source' => 't_DocumentValidations',
            'Description' => 'created by DocumentValidationWorkflowTest',
            'FinalStage' => '',
            'CreatedBy' => $actor->Id,
            'CreatedOn' => now(),
            'ModifiedBy' => $actor->Id,
            'ModifiedOn' => now(),
        ]);

        $this->createdWorkflowIds[] = (int) $workflow->Id;

        $allType = DB::table('t_WorkFlowTypes')->where('TypeID', 'ALL')->value('Id');

        foreach (array_values($approversByStage) as $index => $approver) {
            $order = $index + 1;
            $permissionId = $this->makeStagePermission('teststage_' . Str::lower(Str::random(10)), $approver);

            WorkflowStage::create([
                'Order' => $order,
                'StageName' => 'Test Stage ' . $order . ' ' . Str::random(4),
                'EscalationLimit' => 30,
                'WorkFlowId' => $workflow->Id,
                'WorkFlowTypeId' => $allType,
                'PermissionId' => $permissionId,
                'OverridePermissionId' => $permissionId,
                'Count' => 1,
                'IsActive' => true,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]);
        }

        return $workflow->refresh();
    }

    /**
     * A permission only $holder has - the engine resolves stage approvers by permission, so this
     * is how a stage ends up with exactly one eligible approver.
     */
    protected function makeStagePermission(string $name, User $holder): int
    {
        $permissionTable = config('permission.table_names.permissions');

        DB::table($permissionTable)->insert([
            'name' => $name,
            'guard_name' => 'web',
            'ModuleId' => 700000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = (int) DB::table($permissionTable)->where('name', $name)->value('id');
        $this->createdPermissionIds[] = $permissionId;

        // f_getUserWithPermission matches model_type against the morph alias ('UserID'), not the
        // class name, so a Spatie-style givePermissionTo() grant would be invisible to it.
        DB::table(config('permission.table_names.model_has_permissions'))->insert([
            'permission_id' => $permissionId,
            'model_type' => User::getPrimaryKey(),
            'model_id' => $holder->Id,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $permissionId;
    }

    protected function makeValidation(
        DocumentValidationType $type,
        User $actor,
        ?Document $document = null
    ): DocumentValidation {
        $validation = DocumentValidation::create([
            'Name' => 'Test validation ' . Str::random(6),
            'ValidationId' => $type->ValidationTypeId . '-' . Str::upper(Str::random(6)),
            'ValidationTypeId' => $type->Id,
            'DocumentId' => $document?->Id,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);

        $this->createdValidationIds[] = (int) $validation->Id;

        return $validation;
    }

    /**
     * Register a validation this test did not create directly - e.g. one an endpoint created -
     * so tearDown still removes it.
     */
    protected function trackValidation(int $validationId): void
    {
        $this->createdValidationIds[] = $validationId;
    }

    /**
     * Active stage ids of a workflow, in order. The Workflow model has no stages() relation.
     *
     * @return array<int,int>
     */
    protected function stageIds(Workflow $flow): array
    {
        return WorkflowStage::query()
            ->where('WorkFlowId', $flow->Id)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->orderBy('Order')
            ->pluck('Id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Grant $user the type-level roster role the DocumentValidationPolicy checks.
     */
    protected function grantTypeRole(
        DocumentValidationType $type,
        User $grantee,
        User $actor,
        RoleEnum $role = RoleEnum::Write
    ): void {
        (new ValidationTypeService($type))->addApprover($grantee, $actor, $role, false);
    }

    /**
     * Remove everything these fixtures created, children before parents.
     */
    protected function cleanupDocumentValidationFixtures(): void
    {
        $validationIds = array_unique($this->createdValidationIds);
        $typeIds = array_unique($this->createdTypeIds);
        $workflowIds = array_unique($this->createdWorkflowIds);
        $permissionIds = array_unique($this->createdPermissionIds);
        $userIds = array_unique($this->createdUserIds);

        if ($validationIds) {
            $sourceIds = array_map('strval', $validationIds);
            foreach (['t_WorkFlowPending', 't_WorkFlowHistory'] as $table) {
                DB::table($table)->where('Source', 't_DocumentValidations')
                    ->whereIn('SourceID', $sourceIds)->delete();
            }
            DB::table('t_DocumentValidationAttributes')
                ->whereIn('DocumentValidationId', $validationIds)->delete();
            DB::table('t_DocumentValidations')->whereIn('Id', $validationIds)->delete();
        }

        if ($typeIds) {
            DB::table('t_SpecialPermissions')->where('Model', 'DocumentValidationTypeId')
                ->whereIn('ModelID', $typeIds)->delete();
            DB::table('t_DocumentValidationTypes')->whereIn('Id', $typeIds)->delete();
        }

        // Stages reference the permissions, so they must go first.
        if ($workflowIds) {
            DB::table('t_WorkFlowStages')->whereIn('WorkFlowId', $workflowIds)->delete();
            DB::table('t_Workflows')->whereIn('Id', $workflowIds)->delete();
        }

        if ($permissionIds) {
            foreach (['model_has_permissions', 'role_has_permissions'] as $key) {
                DB::table(config("permission.table_names.{$key}"))
                    ->whereIn('permission_id', $permissionIds)->delete();
            }
            DB::table(config('permission.table_names.permissions'))
                ->whereIn('id', $permissionIds)->delete();
        }

        if ($userIds) {
            // p_sendNotificationEmail queues approval notifications against these users.
            DB::table('t_Emails')->whereIn('CreatedBy', $userIds)
                ->orWhereIn('ModifiedBy', $userIds)->delete();
            DB::table(config('activitylog.table_name'))->whereIn('causer_id', $userIds)->delete();
            DB::table('t_Users')->whereIn('Id', $userIds)->delete();
        }

        $this->createdValidationIds = [];
        $this->createdTypeIds = [];
        $this->createdWorkflowIds = [];
        $this->createdPermissionIds = [];
        $this->createdUserIds = [];
    }
}
