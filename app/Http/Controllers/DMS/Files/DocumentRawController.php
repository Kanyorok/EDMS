<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Services\DMS\DocumentService;
use Illuminate\Http\Response;

class DocumentRawController extends Controller
{
    /**
     * Serves a document's raw, decrypted bytes for inline preview (<img>/<video>/<iframe> src).
     *
     * Reached only via a short-lived signed URL minted by DocumentService::preview() at the
     * moment a caller (e.g. SupplierDocumentPreviewController) has already authorized viewing
     * this specific document - the signature itself is the authorization, so this deliberately
     * does not re-run each page's own bespoke access check. This avoids building the whole file
     * as a base64 string embedded in an HTML page (33% size bloat, several full in-memory copies,
     * and a JS fetch()+innerHTML flow that has to wait for the entire payload) which was slow
     * enough on large files to trip client-side timeouts for anyone not on a fast connection to
     * the server. The browser now requests this URL directly for the <img>/<iframe>/<video>.
     */
    public function show(Document $document): Response
    {
        $service = new DocumentService($document);
        $content = $service->getFileContent(false);

        return response($content, 200)
            ->header('Content-Type', $document->MimeType ?: $service->type->getMimeType())
            ->header('Content-Disposition', 'inline; filename="' . addslashes($document->Name) . '"');
    }
}
