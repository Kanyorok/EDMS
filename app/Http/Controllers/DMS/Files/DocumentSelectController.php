<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSelectController extends Controller
{
    protected const LIMIT = 10;

    /**
     * Select2 search source for picking one document by name - scoped to whatever the
     * requesting user is allowed to see, same as the rest of the DMS.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $search = $request->string('q', '')->squish()->toString();
        if ($search === '') {
            return response()->json([]);
        }

        $documents = Document::query()
            ->user($request->user())
            ->whereHas('current')
            ->where('t_Documents.Name', 'LIKE', "%{$search}%")
            ->orderBy('t_Documents.Name')
            ->limit(self::LIMIT)
            ->get(['DocumentId', 'Name']);

        return response()->json($documents->map(fn (Document $document) => [
            'DocumentId' => $document->DocumentId,
            'Name' => $document->Name,
        ]));
    }
}
