<?php

namespace App\Http\Controllers\DMS\Verification;

use App\Enums\DMS\DocumentValidationModeEnum;
use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\ApproveDocumentValidationRequest;
use App\Http\Requests\DMS\RejectDocumentValidationRequest;
use App\Http\Requests\DMS\UploadDocumentRequest;
use App\Models\Auth\User;
use App\Models\Core\Approval\WorkflowHistory;
use App\Models\Core\Approval\WorkflowStage;
use App\Models\DMS\DMSSignature;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentValidation;
use App\Services\DMS\DocumentService;
use App\Services\DMS\RepositoryService;
use App\Services\DMS\Verification\DocumentValidationWorkflow;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Yajra\DataTables\DataTables;

class DocumentValidationController extends Controller
{
    public function __construct(private readonly DocumentValidationWorkflow $workflow)
    {
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', DocumentValidation::class);

        if ($request->ajax()) {
            $actor = $request->user();
            $isAdmin = $actor->hasRole('admin')
                || $actor->hasRole(\App\Enums\Core\RoleEnum::Admin->value)
                || $actor->can(\App\Enums\Core\PermissionEnum::Admin->value);

            $query = DocumentValidation::query()->where(function ($query) {
                $query->where('Status', '!=', DocumentValidationStatusEnum::Approved->value)
                    ->orWhereNull('DocumentId');
            });

            if (! $isAdmin) {
                $query->where(function ($q) use ($actor) {
                    $q->whereHas('type', function ($tq) use ($actor) {
                        $tq->user($actor);
                    })
                    ->orWhereNull('ValidationTypeId')
                    ->orWhere('CreatedBy', $actor->Id)
                    ->orWhereHas('workflowPending', function ($wpq) use ($actor) {
                        $wpq->where('UserId', $actor->Id)->whereNull('DeletedOn');
                    });
                });
            }

            try {
                return Datatables::of($query->lock('WITH(NOLOCK)')->with('type')->select('*'))->addIndexColumn()
                    ->addColumn('action', function (DocumentValidation $documentValidation) {
                        return '<a href="' . route('dms.validation.show', $documentValidation->ValidationId) . '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> details</a>';
                    })->editColumn('Type', function (DocumentValidation $documentValidation) {
                        return $documentValidation->type?->Name ?? '<span class="badge bg-secondary">Approval Only</span>';
                    })->addColumn('Stage', function (DocumentValidation $documentValidation) {
                        return $this->stageBadge($documentValidation);
                    })->rawColumns(['action', 'Stage', 'Type'])->make();
            } catch (Exception $e) {
                Log::error('failed loading document validation : ' . $e);

                return $this->errored('failed loading document validation.');
            }
        }

        return view('dms.validation.index');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $documentValidationId)
    {
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)
            ->with(['properties', 'creator', 'document', 'type'])->first();
        if (! $documentValidation instanceof DocumentValidation) {
            return redirect()->back()->with('error', 'Invalid validation provided');
        }

        $this->authorize('view', $documentValidation);

        $document = $documentValidation->document;
        if (! $document instanceof Document) {
            $document = null;
        }

        $hasWorkflow        = (bool) $documentValidation->type?->hasWorkflow();
        $actor              = $request->user();
        $mode               = $documentValidation->SubmissionMode ?? DocumentValidationModeEnum::Validation;
        $hasPendingWorkflow = $hasWorkflow && $this->workflow->hasPendingStage($documentValidation);

        if ($hasPendingWorkflow) {
            $canApprove        = $document && $this->workflow->canUserApprove($documentValidation, $actor);
            $isFinalStage      = false;
            $signatureRequired = false;
        } else {
            $isApproved        = $documentValidation->isApproved();
            $canApprove        = $document && ! $isApproved && $actor->can('approve', $documentValidation);
            $isFinalStage      = true;
            $signatureRequired = $mode->requiresSignature();
        }

        // A rejection force-checks the document out for correction (see reject()); it must be
        // checked back in before it can be re-submitted, or the still-broken version would just
        // go straight back through validation.
        $documentCheckedOut = $document && (bool) (new DocumentService($document))->isCheckedOut();

        return view('dms.validation.show', compact('documentValidation', 'document', 'hasWorkflow', 'canApprove', 'mode'))
            ->with('signatures', DMSSignature::query()->userCreator($actor)->lock('WITH(NOLOCK)')->get(["Id", "SignatureId", "Name"]))
            ->with('isFinalStage', $isFinalStage)
            ->with('signatureRequired', $signatureRequired)
            ->with('hasPendingWorkflow', $hasPendingWorkflow)
            ->with('workflowStatus', $hasWorkflow ? $this->safeWorkflowStatus($documentValidation) : [])
            ->with('workflowHistory', $hasWorkflow
                ? $documentValidation->workflowHistory()->with(['creator', 'status', 'stage'])->orderByDesc('CreatedOn')->get()
                : collect())
            ->with('documentCheckedOut', $documentCheckedOut)
            ->with('canResubmit', $documentValidation->isRejected() && $document && $documentValidation->CreatedBy === $actor->Id && ! $documentCheckedOut);
    }

    /**
     * Approve the validation.
     *
     * - Active workflow stage: walk workflow stages.
     *   - ApprovalOnly: at final workflow stage, mark as Approved (no signature).
     *   - ApprovalThenSign: at final workflow stage, complete workflow and transition to validation phase.
     * - No active workflow stage / Validation phase:
     *   - Validation / ApprovalThenSign: collect signature and approve document.
     */
    public function approve(ApproveDocumentValidationRequest $request, $documentValidationId): JsonResponse
    {
        $actor              = $request->user();
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)
            ->whereNull('ApprovedBy')->with(['document', 'type'])->first();
        if (! $documentValidation instanceof DocumentValidation || ! $documentValidation->document instanceof Document) {
            return $this->errored('Invalid validation provided or cannot validate');
        }

        $this->authorize('approve', $documentValidation);

        $hasWorkflow        = (bool) $documentValidation->type?->hasWorkflow();
        $hasPendingWorkflow = $hasWorkflow && $this->workflow->hasPendingStage($documentValidation);
        $mode               = $documentValidation->SubmissionMode ?? DocumentValidationModeEnum::Validation;

        if ($hasPendingWorkflow) {
            $lock = Cache::lock("dms_validation_approve:{$documentValidation->Id}", 20);
            if (! $lock->get()) {
                return $this->errored('This validation is already being processed, try again shortly');
            }

            try {
                if (! $this->workflow->canUserApprove($documentValidation, $actor)) {
                    return $this->errored('You are not a pending approver for the current stage of this validation');
                }

                $this->workflow->approveValidation($documentValidation, $actor, $request->getRemarks());

                $documentValidation->refresh();
                $stillPending = $this->workflow->hasPendingStage($documentValidation);

                if (! $stillPending) {
                    if ($mode === DocumentValidationModeEnum::ApprovalOnly) {
                        DB::transaction(function () use ($documentValidation, $actor) {
                            $documentValidation->update([
                                'Status'     => DocumentValidationStatusEnum::Approved,
                                'ApprovedBy' => $actor->Id,
                                'ApprovedOn' => now(),
                                'ModifiedBy' => $actor->Id,
                            ]);
                        });

                        activity()->causedBy($actor)->performedOn($documentValidation)->event('approve')
                            ->log('Approved document validation (approval only – final stage)');

                        return $this->succeeded('Document approved successfully', route('dms.validation.index'));
                    }

                    // p_ProcessWorkflowAction unconditionally stamps the source row's Status
                    // column with the "Approved" CodeDetail value once the final stage clears
                    // (section 15 of the SP) - the generic engine has no notion of a second
                    // sign-off phase. Every mode except ApprovalOnly still needs that phase, so
                    // put Status back to Submitted: left as "Approved", isApproved() reads true,
                    // the index's `Status != Approved` filter hides the record, and canApprove
                    // in show() goes false - nobody can reach the sign step.
                    DB::transaction(function () use ($documentValidation, $actor) {
                        $documentValidation->update([
                            'Status'     => DocumentValidationStatusEnum::Submitted,
                            'ModifiedBy' => $actor->Id,
                        ]);
                    });

                    activity()->causedBy($actor)->performedOn($documentValidation)->event('approve')
                        ->log('Completed approval workflow; pending final document validation and signature.');

                    return $this->succeeded('Workflow approval completed. Pending validation and signature from assigned approvers.', route('dms.validation.index'));
                }

                activity()->causedBy($actor)->performedOn($documentValidation)->event('approve')
                    ->log('Approved document validation stage');

                return $this->succeeded('Stage approved, sent to the next approver', route('dms.validation.index'));
            } catch (ErroredException $e) {
                return $e->toJson();
            } catch (Throwable $e) {
                Log::error('Error validating document: ' . $e);
            } finally {
                $lock->release();
            }

            return $this->errored('Unexpected error, try again later');
        }

        return $this->approveWithoutWorkflow($request, $documentValidation, $actor);
    }

    /**
     * Reject the validation with a reason, force-checking out the document so the submitter
     * must correct it and check back in. If the document is not checked in within 3 days the
     * nightly cleanup command will move it to trash automatically.
     */
    public function reject(RejectDocumentValidationRequest $request, $documentValidationId): JsonResponse
    {
        $actor = $request->user();
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)
            ->whereNull('ApprovedBy')->with(['document', 'type', 'creator'])->first();
        if (! $documentValidation instanceof DocumentValidation || ! $documentValidation->document instanceof Document) {
            return $this->errored('Invalid validation provided or cannot validate');
        }

        $this->authorize('approve', $documentValidation);

        $hasWorkflow        = (bool) $documentValidation->type?->hasWorkflow();
        // A type can have a workflow bound yet have nothing pending on it right now - the
        // ApprovalThenSign second phase, once the workflow stages are all cleared and the
        // validation just sits waiting for a signature. p_ProcessWorkflowAction requires a live
        // t_WorkFlowPending row for the acting user and RAISERRORs "No pending approval found"
        // otherwise, so rejecting via the workflow engine at that point would always fail. Route
        // that case through the same direct rejection the no-workflow path uses instead -
        // mirrors approve()'s hasPendingWorkflow distinction.
        $hasPendingWorkflow = $hasWorkflow && $this->workflow->hasPendingStage($documentValidation);
        $reason             = $request->getReason();

        try {
            if ($hasPendingWorkflow && ! $this->workflow->canUserApprove($documentValidation, $actor)) {
                return $this->errored('You are not a pending approver for the current stage of this validation');
            }

            if ($hasPendingWorkflow) {
                $this->workflow->rejectValidation($documentValidation, $actor, $reason);
            }

            DB::transaction(function () use ($documentValidation, $actor, $reason, $hasPendingWorkflow) {
                // Deliberately does not touch ApprovedBy/ApprovedOn.
                $documentValidation->update([
                    'Status'          => DocumentValidationStatusEnum::Rejected,
                    'RejectedBy'      => $actor->Id,
                    'RejectedOn'      => now(),
                    'RejectionReason' => $reason,
                    'ModifiedBy'      => $actor->Id,
                ]);

                if (! $hasPendingWorkflow) {
                    $rejectedStatusId = DocumentValidationWorkflow::codeDetail(
                        DocumentValidationStatusEnum::Rejected,
                        DocumentValidationWorkflow::CODE_ID
                    )->ID;

                    WorkflowHistory::create([
                        'Source'     => 't_DocumentValidations',
                        'SourceID'   => (string) $documentValidation->Id,
                        'Stage'      => 0,
                        'StatusId'   => $rejectedStatusId,
                        'Notes'      => $reason,
                        'CreatedBy'  => $actor->Id,
                        'ModifiedBy' => $actor->Id,
                        'CreatedOn'  => now(),
                        'isApproved' => false,
                    ]);
                }
            });

            // Force-checkout the document to the submitter (not the rejecting approver) so the
            // check-in ownership lands on whoever actually has to fix it - mirrors how
            // DocumentReviewController::update() hands a reviewed document back to the requester.
            // The checkout remark is tagged so the auto-trash command can identify it.
            $requester = $documentValidation->creator;
            if ($requester instanceof User) {
                try {
                    (new DocumentService($documentValidation->document))
                        ->forceCheckout(
                            $requester,
                            '[REJECTED] Document rejected: ' . $reason . ' — please correct and check back in within 3 days.'
                        );
                } catch (\Throwable $ce) {
                    // Non-fatal: the rejection itself already succeeded; log but don't roll back.
                    Log::warning('Could not force-checkout document after rejection: ' . $ce->getMessage());
                }
            }

            activity()->causedBy($actor)->performedOn($documentValidation)->event('reject')
                ->log('Rejected document validation: ' . $reason);

            return $this->succeeded('Document validation rejected. Document has been checked out for correction.', route('dms.validation.index'));
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (Throwable $e) {
            Log::error('Error rejecting document validation: ' . $e);
        }

        return $this->errored('Unexpected error, try again later');
    }

    /**
     * Re-submit a rejected validation once the document has been corrected.
     */
    public function resubmit(Request $request, $documentValidationId): JsonResponse
    {
        $actor = $request->user();
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)
            ->with(['document', 'type'])->first();
        if (! $documentValidation instanceof DocumentValidation || ! $documentValidation->document instanceof Document) {
            return $this->errored('Invalid validation provided');
        }

        $this->authorize('update', $documentValidation);

        if (! $documentValidation->isRejected()) {
            return $this->errored('Only a rejected validation can be re-submitted');
        }

        if ((new DocumentService($documentValidation->document))->isCheckedOut()) {
            return $this->errored('Check the document back in before re-submitting it for validation');
        }

        try {
            $documentValidation->update([
                'Status' => DocumentValidationStatusEnum::Pending,
                'RejectedBy' => null,
                'RejectedOn' => null,
                'RejectionReason' => null,
                'ModifiedBy' => $actor->Id,
            ]);

            $this->submitToWorkflow($documentValidation, $actor, 'Re-submitted for validation');

            activity()->causedBy($actor)->performedOn($documentValidation)->event('submit')
                ->log('Re-submitted document validation : ' . $documentValidation->ValidationId);

            return $this->succeeded('Validation re-submitted successfully', route('dms.validation.show', $documentValidation->ValidationId));
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (Throwable $e) {
            Log::error('Error re-submitting document validation: ' . $e);
        }

        return $this->errored('Unexpected error, try again later');
    }

    /**
     * Attach a document to a validation that was created without one (the external API intake),
     * then start its workflow now that there is something to approve.
     */
    public function update(UploadDocumentRequest $request, $documentValidationId): JsonResponse
    {
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)
            ->whereNull('DocumentId')->with('type')->first();
        if (! $documentValidation instanceof DocumentValidation) {
            return $this->errored('Invalid validation provided');
        }

        $this->authorize('update', $documentValidation);

        $actor = $request->user();

        try {
            DB::transaction(function () use ($documentValidation, $request, $actor) {
                $repository = $documentValidation->type
                    ? RepositoryService::validationType($documentValidation->type)
                    : RepositoryService::validation();

                $document = DocumentService::createUpload($repository, $request->file('file'), $actor)->document;
                $documentValidation->update([
                    'DocumentId' => $document->Id,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($documentValidation)->event('document')
                    ->log('Uploaded ' . $document->Name . ' for validation.');
            });

            $this->submitToWorkflow($documentValidation->refresh(), $actor);

            return $this->succeeded('document uploaded successfully', route('dms.validation.show', $documentValidation->ValidationId));
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (Throwable $e) {
            Log::error('Error uploading validation document : ' . $e);

            return $this->errored('unexpected error, try again later');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $documentValidationId): JsonResponse
    {
        $documentValidation = DocumentValidation::query()->where('ValidationId', $documentValidationId)->first();
        if (! $documentValidation instanceof DocumentValidation) {
            return $this->errored('Invalid validation provided');
        }

        $this->authorize('delete', $documentValidation);

        $actor = $request->user();

        try {
            return DB::transaction(function () use ($documentValidation, $actor) {
                $documentValidation->forceFill([
                    'DeletedOn' => now(),
                    'DeletedBy' => $actor->Id,
                ])->save();

                activity()->causedBy($actor)->performedOn($documentValidation)->event('delete')
                    ->log('Trashed document validation ' . $documentValidation->ValidationId);

                return $this->succeeded("validation {$documentValidation->ValidationId} trashed successfully", route('dms.validation.index'));
            });
        } catch (Throwable $e) {
            Log::error('Error deleting document validation : ' . $e);
        }

        return $this->errored('Unexpected error, try again later');
    }

    /**
     * The original single-step path, kept for validation types with no workflow bound.
     *
     * ApprovalOnly mode: mark as Approved without collecting or applying a signature.
     * Validation / ApprovalThenSign: collect a signature and sign the document.
     */
    private function approveWithoutWorkflow(ApproveDocumentValidationRequest $request, DocumentValidation $documentValidation, User $actor): JsonResponse
    {
        $mode = $documentValidation->SubmissionMode ?? DocumentValidationModeEnum::Validation;
        $remarks = $request->getRemarks();

        try {
            $approvedStatusId = DocumentValidationWorkflow::codeDetail(
                DocumentValidationStatusEnum::Approved,
                DocumentValidationWorkflow::CODE_ID
            )->ID;

            if (! $mode->requiresSignature()) {
                // ApprovalOnly – approve without a signature.
                return DB::transaction(function () use ($documentValidation, $actor, $remarks, $approvedStatusId) {
                    $documentValidation->update([
                        'Status'     => DocumentValidationStatusEnum::Approved,
                        'ApprovedBy' => $actor->Id,
                        'ApprovedOn' => now(),
                        'ModifiedBy' => $actor->Id,
                    ]);

                    WorkflowHistory::create([
                        'Source'     => 't_DocumentValidations',
                        'SourceID'   => (string) $documentValidation->Id,
                        'Stage'      => 0,
                        'StatusId'   => $approvedStatusId,
                        'Notes'      => $remarks,
                        'CreatedBy'  => $actor->Id,
                        'ModifiedBy' => $actor->Id,
                        'CreatedOn'  => now(),
                        'isApproved' => true,
                    ]);

                    activity()->causedBy($actor)->performedOn($documentValidation)->event('approve')
                        ->log('Approved document validation (approval only): ' . $remarks);

                    return $this->succeeded('Document approved successfully', route('dms.validation.index'));
                });
            }

            // Validation / ApprovalThenSign – approve and sign.
            $signature = $request->getSignature($actor);

            return DB::transaction(function () use ($documentValidation, $actor, $signature, $remarks, $approvedStatusId) {
                $documentValidation->update([
                    'Status'     => DocumentValidationStatusEnum::Approved,
                    'ApprovedBy' => $actor->Id,
                    'ApprovedOn' => now(),
                    'ModifiedBy' => $actor->Id,
                ]);

                WorkflowHistory::create([
                    'Source'     => 't_DocumentValidations',
                    'SourceID'   => (string) $documentValidation->Id,
                    'Stage'      => 0,
                    'StatusId'   => $approvedStatusId,
                    'Notes'      => $remarks,
                    'CreatedBy'  => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                    'CreatedOn'  => now(),
                    'isApproved' => true,
                ]);

                activity()->causedBy($actor)->performedOn($documentValidation)->event('approve')
                    ->log('Approved and signed document validation: ' . $remarks);

                (new DocumentService($documentValidation->document))->sign($signature, $actor, 1);

                return $this->succeeded('Document validated successfully', route('dms.validation.index'));
            });
        } catch (ErroredException $e) {
            return $e->toJson();
        } catch (Throwable $e) {
            Log::error('Error validating document: ' . $e);
        }

        return $this->errored('Unexpected error, try again later');
    }

    /**
     * Start the type's workflow, if it has one. A no-op for legacy single-approval types or Submit for Validation mode.
     */
    private function submitToWorkflow(DocumentValidation $documentValidation, User $actor, string $remarks = 'Submitted for validation'): void
    {
        if (! $documentValidation->type?->hasWorkflow() || ! $documentValidation->DocumentId) {
            return;
        }

        if ($documentValidation->SubmissionMode === DocumentValidationModeEnum::Validation) {
            return;
        }

        $this->workflow->submitValidation($documentValidation, $actor, $remarks);
    }

    private function stageBadge(DocumentValidation $documentValidation): string
    {
        if (is_null($documentValidation->DocumentId)) {
            return '<span class="badge rounded-pill bg-primary">Pending Document</span>';
        }

        $status = $documentValidation->Status ?? DocumentValidationStatusEnum::Pending;
        $label = $status->description();

        // While a validation is in flight, show which stage it is waiting on rather than
        // the generic 'Submitted for Approval'.
        if ($status === DocumentValidationStatusEnum::Submitted) {
            $hasPending = $documentValidation->workflowPending()->whereNull('DeletedOn')->exists();
            if ($hasPending) {
                $stageId = $documentValidation->workflowPending()->whereNull('DeletedOn')->latest('CreatedOn')->value('Stage');
                $label = ($stageId ? WorkflowStage::query()->whereKey($stageId)->value('StageName') : null) ?? $label;
            } elseif ($documentValidation->SubmissionMode === DocumentValidationModeEnum::ApprovalThenSign) {
                $label = 'Pending Validation';
            }
        }

        return '<span class="badge rounded-pill ' . $status->badge() . '">' . e($label) . '</span>';
    }

    /**
     * getStatus() reaches into the workflow tables and can throw on half-configured workflows;
     * the detail page must still render when it does.
     */
    private function safeWorkflowStatus(DocumentValidation $documentValidation): array
    {
        try {
            return $this->workflow->statusFor($documentValidation);
        } catch (Throwable $e) {
            Log::warning('Could not resolve document validation workflow status: ' . $e->getMessage());

            return [];
        }
    }
}
