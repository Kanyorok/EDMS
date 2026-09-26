<?php

namespace App\Http\Controllers\DMS\Files;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Services\DMS\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentArchiveController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Archive.
     */
    public function store(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        $reason = trim((string) $request->input('ArchiveReason', ''));

        try {
            (new DocumentService($document))->archive($request->user(), $reason !== '' ? mb_substr($reason, 0, 50) : 'manual');
        } catch (ErroredException $e) {
            return $e->toJson();
        }

        return $this->succeeded('document archived successfully');
    }

    /**
     * Unarchive.
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        $this->authorize('update', $document);

        try {
            (new DocumentService($document))->unarchive($request->user());
        } catch (ErroredException $e) {
            return $e->toJson();
        }

        return $this->succeeded('document unarchived successfully');
    }
}
