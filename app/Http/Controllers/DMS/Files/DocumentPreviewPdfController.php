<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Services\DMS\DocumentService;
use App\Services\DMS\FileConversionService;
use Illuminate\Http\Response;

class DocumentPreviewPdfController extends Controller
{
    /**
     * Serves a LibreOffice-converted PDF rendition of a Word/RTF/ODT document for inline
     * <iframe> preview. Reached only via a short-lived signed URL minted by
     * DocumentService::preview() - see DocumentRawController for why the signature alone is
     * treated as authorization here.
     */
    public function show(Document $document): Response
    {
        $service = new DocumentService($document);
        abort_unless($service->type->isDocument(), 404);

        $pdfPath = (new FileConversionService($document))->convertToPdf();
        abort_if($pdfPath === null || ! file_exists($pdfPath), 503, 'Preview conversion failed, please download the file instead.');

        return response(file_get_contents($pdfPath), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . addslashes(pathinfo($document->Name, PATHINFO_FILENAME)) . '.pdf"');
    }
}
