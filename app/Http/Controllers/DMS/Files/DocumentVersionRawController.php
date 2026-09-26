<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentVersion;
use App\Services\DMS\DocumentService;
use Illuminate\Http\Response;

class DocumentVersionRawController extends Controller
{
    /**
     * Serves a specific historical version's raw, decrypted bytes for inline preview
     * (<img>/<video>/<iframe> src). Reached only via a short-lived signed URL minted by
     * DocumentService::previewVersion() - see DocumentRawController for why the signature
     * alone is treated as authorization here.
     */
    public function show(Document $document, DocumentVersion $version): Response
    {
        abort_unless($version->DocumentId === $document->Id, 404);

        $service = new DocumentService($document);
        $content = $service->getFileContent(false, $version);

        return response($content, 200)
            ->header('Content-Type', $service->typeOf($version)->getMimeType() ?: 'application/octet-stream')
            ->header('Content-Disposition', 'inline; filename="' . addslashes($version->Name) . '"');
    }
}
