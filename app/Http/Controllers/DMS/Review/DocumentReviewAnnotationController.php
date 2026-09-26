<?php

namespace App\Http\Controllers\DMS\Review;

use App\Http\Controllers\Controller;
use App\Models\DMS\DocumentReview;
use App\Models\DMS\DocumentReviewAnnotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentReviewAnnotationController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Drop a pinned comment on the document at a specific page/position.
     */
    public function store(Request $request, DocumentReview $documentReview): JsonResponse
    {
        $this->authorize('participate', $documentReview);

        $data = $request->validate([
            'Page' => ['required', 'integer', 'min:1'],
            'X' => ['required', 'numeric', 'min:0', 'max:1'],
            'Y' => ['required', 'numeric', 'min:0', 'max:1'],
            'Comment' => ['required', 'string', 'max:2000'],
        ]);

        $actor = $request->user();
        $annotation = $documentReview->annotations()->create([
            'Page' => $data['Page'],
            'X' => $data['X'],
            'Y' => $data['Y'],
            'Comment' => $data['Comment'],
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);
        $annotation->load('creator');

        return $this->succeeded('annotation added', data: [
            'data' => $this->present($annotation, $actor->Id),
        ]);
    }

    /**
     * Remove an annotation. Only its own author may remove it.
     */
    public function destroy(Request $request, DocumentReview $documentReview, DocumentReviewAnnotation $annotation): JsonResponse
    {
        $this->authorize('participate', $documentReview);
        abort_unless($annotation->DocumentReviewId === $documentReview->Id, 404);

        if ($annotation->CreatedBy !== $request->user()->Id) {
            return $this->errored('you can only remove your own annotation.', status: 403);
        }

        $annotation->forceFill(['DeletedBy' => $request->user()->Id])->delete();

        return $this->succeeded('annotation removed');
    }

    private function present(DocumentReviewAnnotation $annotation, int $actorId): array
    {
        return [
            'Id' => $annotation->Id,
            'Page' => $annotation->Page,
            'X' => (float) $annotation->X,
            'Y' => (float) $annotation->Y,
            'Comment' => $annotation->Comment,
            'Author' => $annotation->creator->Name ?? 'Unknown',
            'CreatedOn' => $annotation->CreatedOn->format('d M Y, H:i'),
            'IsMine' => $annotation->CreatedBy === $actorId,
        ];
    }
}
