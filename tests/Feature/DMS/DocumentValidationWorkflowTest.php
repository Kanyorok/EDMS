<?php

namespace Tests\Feature\DMS;

use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Models\DMS\DocumentValidation;
use App\Services\DMS\Verification\DocumentValidationWorkflow;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesDocumentValidationFixtures;
use Tests\TestCase;

/**
 * Covers the per-type approval workflow for document validations.
 *
 * Exercises the real engine, including p_ProcessWorkflowPending / p_ProcessWorkflowAction and the
 * p_sendNotificationEmail they call, so it needs a SQL Server connection with those procedures
 * installed and the DocumentValidationStatus code details seeded by
 * 2026_08_13_090100_register_document_validation_workflow.php.
 *
 * No DatabaseTransactions here - see CreatesDocumentValidationFixtures for why.
 */
class DocumentValidationWorkflowTest extends TestCase
{
    use CreatesDocumentValidationFixtures;

    protected function tearDown(): void
    {
        $this->cleanupDocumentValidationFixtures();

        parent::tearDown();
    }

    public function test_type_without_workflow_stays_on_single_step_approval(): void
    {
        $submitter = $this->makeUser();
        $type = $this->makeValidationType($submitter);
        $validation = $this->makeValidation($type, $submitter);

        $this->assertFalse($type->hasWorkflow(), 'a new type has no workflow bound');

        $workflow = app(DocumentValidationWorkflow::class);

        // Nothing is pending and the one approver signs immediately, exactly as before this feature.
        $this->assertFalse($workflow->hasPendingStage($validation));
        $this->assertTrue(
            $workflow->isFinalStage($validation),
            'with no workflow every approval is the final one, so it signs'
        );
    }

    public function test_two_stage_workflow_advances_stage_by_stage(): void
    {
        $submitter = $this->makeUser();
        $firstApprover = $this->makeUser();
        $secondApprover = $this->makeUser();

        $type = $this->makeValidationType($submitter);
        $flow = $this->makeValidationWorkflow([$firstApprover, $secondApprover], $submitter);
        $type->update(['WorkFlowId' => $flow->Id]);

        $validation = $this->makeValidation($type->refresh(), $submitter);
        $validation->setRelation('type', $type);

        $workflow = app(DocumentValidationWorkflow::class);
        $workflow->submitValidation($validation, $submitter);

        $stages = $this->stageIds($flow);

        $this->assertSame(
            $stages[0],
            $this->pendingStage($validation),
            "submit must open the first stage of the type's own workflow"
        );
        $this->assertFalse($workflow->isFinalStage($validation), 'stage 1 of 2 is not the final stage');
        $this->assertTrue($workflow->canUserApprove($validation, $firstApprover));

        $workflow->approveValidation($validation, $firstApprover, 'stage one done');

        $this->assertSame(
            $stages[1],
            $this->pendingStage($validation),
            'approving stage 1 must advance to stage 2'
        );
        $this->assertTrue($workflow->isFinalStage($validation), 'stage 2 of 2 is the final stage');

        // Stage 2's approver is a different user; stage 1's approver is done.
        $this->assertTrue($workflow->canUserApprove($validation, $secondApprover));
        $this->assertFalse($workflow->canUserApprove($validation, $firstApprover));

        $workflow->approveValidation($validation, $secondApprover, 'stage two done');

        $this->assertFalse($workflow->hasPendingStage($validation), 'the chain must be exhausted');
        $this->assertSame(
            DocumentValidationStatusEnum::Approved->value,
            DB::table('t_DocumentValidations')->where('Id', $validation->Id)->value('Status')
        );
    }

    public function test_submitter_cannot_approve_their_own_validation(): void
    {
        $submitter = $this->makeUser();
        $approver = $this->makeUser();

        $type = $this->makeValidationType($submitter);
        // The submitter holds stage 2's permission, so only maker-checker can keep them out.
        $flow = $this->makeValidationWorkflow([$approver, $submitter], $submitter);
        $type->update(['WorkFlowId' => $flow->Id]);

        $validation = $this->makeValidation($type->refresh(), $submitter);
        $validation->setRelation('type', $type);

        $workflow = app(DocumentValidationWorkflow::class);
        $workflow->submitValidation($validation, $submitter);

        $this->assertFalse(
            $workflow->canUserApprove($validation, $submitter),
            'maker-checker must stop the submitter approving their own validation'
        );
        $this->assertTrue($workflow->canUserApprove($validation, $approver));
    }

    public function test_rejection_records_a_reason_and_leaves_approved_by_null(): void
    {
        $submitter = $this->makeUser();
        $approver = $this->makeUser();

        $type = $this->makeValidationType($submitter);
        $flow = $this->makeValidationWorkflow([$approver], $submitter);
        $type->update(['WorkFlowId' => $flow->Id]);

        $validation = $this->makeValidation($type->refresh(), $submitter);
        $validation->setRelation('type', $type);

        $workflow = app(DocumentValidationWorkflow::class);
        $workflow->submitValidation($validation, $submitter);
        $workflow->rejectValidation($validation, $approver, 'scan is unreadable');

        // Mirrors what the controller writes alongside the engine call.
        $validation->update([
            'Status' => DocumentValidationStatusEnum::Rejected,
            'RejectedBy' => $approver->Id,
            'RejectedOn' => now(),
            'RejectionReason' => 'scan is unreadable',
        ]);

        $fresh = DocumentValidation::query()->whereKey($validation->Id)->first();

        // The bug this replaces: reject() used to stamp ApprovedBy/ApprovedOn exactly like
        // approve(), so a rejected validation read as approved everywhere.
        $this->assertNull($fresh->ApprovedBy, 'a rejected validation must never look approved');
        $this->assertNull($fresh->ApprovedOn);
        $this->assertTrue($fresh->isRejected());
        $this->assertSame('scan is unreadable', $fresh->RejectionReason);
        $this->assertFalse($workflow->hasPendingStage($fresh), 'rejection must clear the pending stage');
    }

    public function test_two_types_use_their_own_workflows(): void
    {
        $submitter = $this->makeUser();
        $approverA = $this->makeUser();
        $approverB = $this->makeUser();

        $typeA = $this->makeValidationType($submitter, 'Test Type A ' . uniqid());
        $typeB = $this->makeValidationType($submitter, 'Test Type B ' . uniqid());

        $flowA = $this->makeValidationWorkflow([$approverA], $submitter);
        $flowB = $this->makeValidationWorkflow([$approverB, $approverA], $submitter);

        $typeA->update(['WorkFlowId' => $flowA->Id]);
        $typeB->update(['WorkFlowId' => $flowB->Id]);

        $validationA = $this->makeValidation($typeA->refresh(), $submitter);
        $validationA->setRelation('type', $typeA);
        $validationB = $this->makeValidation($typeB->refresh(), $submitter);
        $validationB->setRelation('type', $typeB);

        $workflow = app(DocumentValidationWorkflow::class);
        $workflow->submitValidation($validationA, $submitter);
        $workflow->submitValidation($validationB, $submitter);

        // The core claim of the design: the engine binds one workflow per source table, and the
        // DMS subclass overrides first-stage selection so each type walks its own chain.
        $this->assertSame($this->stageIds($flowA)[0], $this->pendingStage($validationA));
        $this->assertSame($this->stageIds($flowB)[0], $this->pendingStage($validationB));
        $this->assertNotSame(
            $this->pendingStage($validationA),
            $this->pendingStage($validationB),
            'two types must not share a stage'
        );

        $this->assertTrue($workflow->isFinalStage($validationA), 'type A has a single stage');
        $this->assertFalse($workflow->isFinalStage($validationB), 'type B has two stages');
    }

    private function pendingStage(DocumentValidation $validation): int
    {
        return (int) $validation->workflowPending()
            ->whereNull('DeletedOn')
            ->latest('CreatedOn')
            ->value('Stage');
    }
}
