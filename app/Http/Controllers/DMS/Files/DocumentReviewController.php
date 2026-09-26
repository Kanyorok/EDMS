<?php

namespace App\Http\Controllers\DMS\Files;

use App\Enums\DMS\DocumentReviewStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\DocumentReviewRequest;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentReview;
use App\Services\CRMEmailService;
use App\Services\DMS\DocumentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DocumentReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Show the form for requesting a review.
     */
    public function index(Document $document): View
    {
        $this->authorize('update', $document);

        return view('dms.files.review-request')->with('file', $document);
    }

    /**
     * Store a newly requested review.
     */
    public function store(DocumentReviewRequest $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);
        $actor = $request->user();
        $reviewer = $request->getReviewer();

        if ((new DocumentService($document))->isUnderReview()) {
            return $this->errored('document already has a pending review.');
        }

        $currentVersion = $document->current;
        if (! $currentVersion) {
            return $this->errored('document has no content to review.');
        }

        try {
            return DB::transaction(function () use ($document, $currentVersion, $reviewer, $request, $actor) {
                $note = $request->string('RequestNote', '')->trim()->toString();

                $review = DocumentReview::create([
                    'DocumentId' => $document->Id,
                    'VersionId' => $currentVersion->Id,
                    'ReviewerId' => $reviewer->Id,
                    'Status' => DocumentReviewStatusEnum::Pending->value,
                    'RequestNote' => $note !== '' ? $note : null,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($document)->event('review-requested')->log('requested review of ' . $document->Name . ' from ' . $reviewer->Name);

                CRMEmailService::createUser(
                    user: $reviewer,
                    subject: 'Document Review Requested: ' . $document->Name,
                    body: '<p>Hi ' . $reviewer->Name . ', <br>' . $actor->Name . ' has requested your review of <strong>' . $document->Name . '</strong>.'
                        . ($review->RequestNote ? '<br>Note: ' . $review->RequestNote : '') . '</p>',
                    actor: $actor
                );

                return $this->succeeded('review requested successfully', route('files.show', [$document->repository->RepositoryId, $document->DocumentId]));
            });
        } catch (Throwable | Exception $e) {
            Log::error('requesting document review failed :');
            Log::error($e);
        }

        return $this->errored('an unexpected error occurred');
    }
}
