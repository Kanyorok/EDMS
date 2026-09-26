<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DMSFolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Creates a new case Folio for the upload form's "New case" option, seeded
 * with a "{Code}-0001"-style code (DMSDocumentType::nextFolio()). Called
 * once per batch, client-side - not per file - and the returned FolioId is
 * reused across every file in that Dropzone batch.
 */
class DocumentFolioController extends Controller
{
    public function __invoke(Request $request, DMSDocumentType $documentType): JsonResponse
    {
        $folio = DMSFolio::create([
            'Code' => $documentType->nextFolio(),
            'CreatedBy' => $request->user()->Id,
            'ModifiedBy' => $request->user()->Id,
        ]);

        return $this->succeeded('folio created', data: ['data' => ['id' => $folio->Id, 'code' => $folio->Code]]);
    }
}
