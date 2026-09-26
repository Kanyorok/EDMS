<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Self-heals workflow submissions that got stuck with zero eligible
 * approvers pending - typically because nobody held the required role or
 * permission yet at submission/advancement time - by retrying
 * pending-approval creation once eligible users may have changed.
 *
 * Every module (Dealers, Suppliers, Requisitions, Disposal Plans, ...) uses
 * its own t_CodeDetails CodeID group for its status codes, so there is no
 * single "Submitted"/"Approved"/"Rejected" id to hardcode. Instead, each
 * candidate's latest history row is classified dynamically via its
 * CodeDetail Description, mirroring the same approve/reject substring
 * convention p_ProcessWorkflowAction already uses for its own classification.
 */
class WorkflowReconciliationService
{
    private const MAX_CANDIDATES_PER_RUN = 1000;

    /**
     * @return array{checked: int, healed: int, stillStuck: int}
     */
    public function reconcileStuckApprovals(): array
    {
        $checked = 0;
        $healed = 0;
        $stillStuck = 0;

        foreach ($this->latestHistoryPerSubmission() as $row) {
            $checked++;

            $hasPending = DB::table('t_WorkFlowPending')
                ->where('Source', $row->Source)
                ->where('SourceID', $row->SourceID)
                ->whereNull('DeletedOn')
                ->exists();

            if ($hasPending) {
                continue; // Someone is already pending action on this - not stuck.
            }

            $stageId = $this->targetStageForReconciliation($row);

            if (! $stageId) {
                continue; // Terminal (approved/rejected) or unresolvable - nothing to do.
            }

            if ($this->retryPendingCreation($row->Source, $row->SourceID, $stageId)) {
                $healed++;
            } else {
                $stillStuck++;
            }
        }

        if ($healed > 0 || $stillStuck > 0) {
            Log::info('Workflow reconciliation run complete', compact('checked', 'healed', 'stillStuck'));
        }

        return compact('checked', 'healed', 'stillStuck');
    }

    /**
     * One row per Source+SourceID: its most recent (highest Id) history entry.
     */
    private function latestHistoryPerSubmission(): \Illuminate\Support\Collection
    {
        return DB::table('t_WorkFlowHistory as h')
            ->select('h.Id', 'h.Source', 'h.SourceID', 'h.Stage', 'h.StatusId')
            ->whereNull('h.DeletedOn')
            ->whereIn('h.Id', function ($query) {
                $query->selectRaw('MAX(h2.Id)')
                    ->from('t_WorkFlowHistory as h2')
                    ->whereNull('h2.DeletedOn')
                    ->groupBy('h2.Source', 'h2.SourceID');
            })
            ->limit(self::MAX_CANDIDATES_PER_RUN)
            ->get();
    }

    /**
     * Determine which stage (if any) still needs pending-approval rows created,
     * based on what the latest history entry actually represents.
     */
    private function targetStageForReconciliation(object $row): ?int
    {
        $stageId = is_numeric($row->Stage) ? (int) $row->Stage : null;

        if (! $stageId) {
            return null;
        }

        $description = DB::table('t_CodeDetails')->where('ID', $row->StatusId)->value('Description') ?? '';

        if (stripos($description, 'reject') !== false) {
            return null; // Terminal - pending was correctly cleared on rejection.
        }

        if (stripos($description, 'approve') !== false) {
            // Latest action was an approval - only stuck if there's a next stage
            // whose pending rows never got created (advanceToNextStage failed).
            return $this->nextStageIdAfter($stageId);
        }

        // Anything else (Submitted / Pending / module-specific wording) is a
        // submission-type entry still awaiting routing at its own stage.
        return $stageId;
    }

    private function nextStageIdAfter(int $stageId): ?int
    {
        $stage = DB::table('t_WorkFlowStages')->where('Id', $stageId)->whereNull('DeletedOn')->first();

        if (! $stage) {
            return null;
        }

        $next = DB::table('t_WorkFlowStages')
            ->where('WorkFlowId', $stage->WorkFlowId)
            ->where('Order', '>', $stage->Order)
            ->whereNull('DeletedOn')
            ->where('IsActive', true)
            ->orderBy('Order')
            ->first();

        return $next->Id ?? null;
    }

    private function retryPendingCreation(string $source, string $sourceId, int $stageId): bool
    {
        try {
            $result = DB::select(
                'EXEC p_ProcessWorkflowPending @Source = ?, @SourceID = ?, @StageID = ?',
                [$source, $sourceId, $stageId]
            );

            $succeeded = ! empty($result) && ($result[0]->Status ?? null) === 'SUCCESS';

            if ($succeeded) {
                Log::info('Workflow reconciliation: pending approvals created', [
                    'source' => $source,
                    'source_id' => $sourceId,
                    'stage_id' => $stageId,
                    'eligible_users' => $result[0]->EligibleUserCount ?? null,
                ]);
            }

            return $succeeded;
        } catch (Throwable $e) {
            // Most commonly: still no eligible approvers for this stage. Leave it
            // for the next run rather than failing the whole reconciliation batch.
            Log::info('Workflow reconciliation: still stuck', [
                'source' => $source,
                'source_id' => $sourceId,
                'stage_id' => $stageId,
                'reason' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
