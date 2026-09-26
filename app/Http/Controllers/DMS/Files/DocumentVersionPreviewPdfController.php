<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentVersion;
use App\Services\DMS\DocumentService;
use App\Services\DMS\FileConversionService;
use Illuminate\Http\Response;

class DocumentVersionPreviewPdfController extends Controller
{
    /**
     * Serves a LibreOffice-converted PDF rendition of a specific historical Word/RTF/ODT
     * version for inline <iframe> preview. Reached only via a short-lived signed URL minted
     * by DocumentService::previewVersion() - see DocumentRawController for why the signature
     * alone is treated as authorization here.
     */
    public function show(Document $document, DocumentVersion $version): Response
    {
        abort_unless($version->DocumentId === $document->Id, 404);

        $service = new DocumentService($document);
        abort_unless($service->typeOf($version)->isDocument(), 404);

        $pdfPath = (new FileConversionService($document))->convertToPDF($version);
        abort_if($pdfPath === null || ! file_exists($pdfPath), 503, 'Preview conversion failed, please download the file instead.');

        return response(file_get_contents($pdfPath), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . addslashes(pathinfo($version->Name, PATHINFO_FILENAME)) . '.pdf"');
    }
}
