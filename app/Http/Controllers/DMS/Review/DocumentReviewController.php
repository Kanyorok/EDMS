<?php

namespace App\Http\Controllers\DMS\Review;

use App\Enums\Core\ExtensionsEnum;
use App\Enums\DMS\DocumentReviewStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Auth\User;
use App\Models\DMS\DocumentReview;
use App\Services\DMS\DocumentService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class DocumentReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->only(['update']);
    }

    /**
     * Reviews the current user is either the reviewer or the requester for.
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', DocumentReview::class);

        if ($request->ajax()) {
            $user = $request->user();
            // A review assigned to a team sits in every member's inbox.
            $teamIds = $user->teams()->pluck('t_Teams.TeamID');
            $query = DocumentReview::query()->where(function ($q) use ($user, $teamIds) {
                $q->where('ReviewerId', $user->Id)->orWhere('CreatedBy', $user->Id)
                    ->orWhereIn('ReviewerTeamId', $teamIds);
            });

            try {
                return Datatables::of($query->with(['document', 'reviewer', 'team', 'creator'])->select('t_DocumentReviews.*'))->addIndexColumn()
                    ->addColumn('action', function (DocumentReview $review) {
                        return '<a href="' . route('dms.review.show', $review->Id) . '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> open</a>';
                    })
                    ->addColumn('Document', fn (DocumentReview $review) => $review->document->Name ?? '')
                    ->addColumn('Role', function (DocumentReview $review) use ($user) {
                        return $review->isReviewer($user)
                            ? '<span class="badge bg-info">Reviewer</span>'
                            : '<span class="badge bg-secondary">Requester</span>';
                    })
                    ->addColumn('Reviewer', fn (DocumentReview $review) => $review->reviewerName())
                    ->addColumn('Requester', fn (DocumentReview $review) => $review->creator->Name ?? '')
                    ->editColumn('Status', fn (DocumentReview $review) => '<span class="badge ' . $review->Status->badge() . '">' . $review->Status->description() . '</span>')
                    ->editColumn('CreatedOn', fn (DocumentReview $review) => $review->CreatedOn->format('d M, Y H:i'))
                    ->rawColumns(['action', 'Role', 'Status'])
                    ->make();
            } catch (Exception $e) {
                Log::error('failed loading document reviews : ' . $e);

                return $this->errored('failed loading document reviews.');
            }
        }

        return view('dms.review.index');
    }

    /**
     * The annotation workspace for a single review round.
     */
    public function show(DocumentReview $documentReview): View
    {
        $this->authorize('view', $documentReview);
        $documentReview->load(['document.repository', 'version', 'reviewer', 'team', 'creator', 'annotations.creator']);

        $service = new DocumentService($documentReview->document);
        $type = $service->typeOf($documentReview->version);

        $previewUrl = null;
        $previewKind = null;
        if ($type->value === ExtensionsEnum::Pdf->value) {
            $previewUrl = URL::temporarySignedRoute('dms.file.version-raw', now()->addHours(2), ['document' => $documentReview->document->DocumentId, 'version' => $documentReview->version->Id]);
            $previewKind = 'pdf';
        } elseif ($type->isDocument()) {
            $previewUrl = URL::temporarySignedRoute('dms.file.version-preview-pdf', now()->addHours(2), ['document' => $documentReview->document->DocumentId, 'version' => $documentReview->version->Id]);
            $previewKind = 'pdf';
        } elseif ($type->isImage()) {
            $previewUrl = URL::temporarySignedRoute('dms.file.version-raw', now()->addHours(2), ['document' => $documentReview->document->DocumentId, 'version' => $documentReview->version->Id]);
            $previewKind = 'image';
        }

        return view('dms.review.show')
            ->with('review', $documentReview)
            ->with('file', $documentReview->document)
            ->with('previewUrl', $previewUrl)
            ->with('previewKind', $previewKind);
    }

    /**
     * Mark the review as complete. Reviewer only.
     */
    public function update(Request $request, DocumentReview $documentReview): JsonResponse
    {
        $this->authorize('update', $documentReview);
        $request->validate(['ReviewComment' => ['nullable', 'string', 'max:2000']]);

        if ($documentReview->isReviewed()) {
            return $this->errored('this review has already been marked as reviewed.');
        }

        try {
            return DB::transaction(function () use ($documentReview, $request) {
                $comment = $request->string('ReviewComment', '')->trim()->toString();

                $documentReview->update([
                    // A team review has no named reviewer until a member completes it; record who did.
                    'ReviewerId' => $documentReview->ReviewerId ?? $request->user()->Id,
                    'Status' => DocumentReviewStatusEnum::Reviewed->value,
                    'ReviewComment' => $comment !== '' ? $comment : null,
                    'ReviewedOn' => now(),
                    'ModifiedBy' => $request->user()->Id,
                ]);

                activity()->causedBy($request->user())->performedOn($documentReview->document)->event('reviewed')->log('marked review of ' . $documentReview->document->Name . ' as reviewed');

                // The freeze is lifted the moment Status flips above, so the review
                // requester needs the document handed straight back to them - force
                // checkout skips the normal guards to hand it out before anyone else can.
                //
                // Not for a document an external system owns: its requester is a service account
                // that can never check the file back in, which would leave the document checked out
                // for good. The external system replaces the file through the API instead.
                $requester = $documentReview->creator;
                if ($requester instanceof User && $documentReview->document->SourceSystemId === null) {
                    (new DocumentService($documentReview->document))
                        ->forceCheckout($requester, 'document reviewed - check in the corrected file');
                }

                return $this->succeeded('review marked as complete');
            });
        } catch (Throwable | Exception $e) {
            Log::error('marking document review complete failed :');
            Log::error($e);
        }

        return $this->errored('an unexpected error occurred');
    }
}
