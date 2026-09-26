<?php

namespace App\Http\Controllers\DMS\Files;

use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\DocumentValidationRequest;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;
use App\Services\DMS\Verification\DocumentValidationWorkflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DocumentValidationController extends Controller
{
    public function __construct(private readonly DocumentValidationWorkflow $workflow)
    {
        $this->middleware('ajax');
    }

    /**
     * Display the validation request form.
     */
    public function index(Document $document): JsonResponse|View
    {
        $this->authorize('update', $document);
        if (! $document->ext()?->canSign()) {
            return $this->errored('Document cannot be signed');
        }

        return view('dms.files.validation')
            ->with('file', $document)
            ->with('types', DocumentValidationType::get(['ValidationTypeId', 'Name']));
    }

    /**
     * Store a newly created resource in storage.
     *
     * For ApprovalOnly mode without a bound type, the validation is created with a null
     * ValidationTypeId and relies on the standard approver-roster path rather than a
     * type-specific workflow.
     */
    public function store(DocumentValidationRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $actor          = $request->user();
        $submissionMode = $request->getSubmissionMode();
        $type           = $request->getValidationType();           // null for ApprovalOnly without a type
        $validationId   = $request->createValidationId($type);

        try {
            $validation = DB::transaction(function () use ($actor, $document, $type, $validationId, $submissionMode) {
                $validation = DocumentValidation::create([
                    'Name'             => 'Validate ' . $document->Name,
                    'ValidationId'     => $validationId,
                    'DocumentId'       => $document->Id,
                    'ValidationTypeId' => $type?->Id,             // null is fine — column is nullable
                    'Status'           => DocumentValidationStatusEnum::Pending,
                    'SubmissionMode'   => $submissionMode,
                    'CreatedBy'        => $actor->Id,
                    'ModifiedBy'       => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($validation)->event('create')
                    ->log('submitted document for validation (' . $submissionMode->label() . ') : ' . $validation->ValidationId);

                return $validation;
            });

            // Only start the workflow when a type with a workflow is attached and submission mode is not Submit for Validation.
            if ($type?->hasWorkflow() && $submissionMode !== \App\Enums\DMS\DocumentValidationModeEnum::Validation) {
                $this->workflow->submitValidation($validation->setRelation('type', $type), $actor);
            }

            return $this->succeeded('validation submitted successfully');
        } catch (\Throwable $e) {
            Log::error('Could not submit document for validation ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }
}
