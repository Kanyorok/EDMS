<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\LinkedToType;
use Illuminate\Http\JsonResponse;

/**
 * Behind the upload form's Linked-to Type dropdown: filters the options to
 * whichever Linked-to Type(s) the chosen Document Type is configured for.
 * A Document Type with no configured applicability (the pivot is empty -
 * still true for most seeded types until an admin configures it) is
 * treated as unrestricted, same as before this endpoint existed, rather
 * than offering nothing.
 */
class DocumentTypeLinkedToTypesController extends Controller
{
    public function __invoke(DMSDocumentType $documentType): JsonResponse
    {
        $types = $documentType->linkedToTypes()->where('IsActive', true)->orderBy('Name')->get(['t_DMSLinkedToTypes.Id', 'Code', 'Name', 'SourceRelation']);

        if ($types->isEmpty()) {
            $types = LinkedToType::query()->where('IsActive', true)->orderBy('Name')->get(['Id', 'Code', 'Name', 'SourceRelation']);
        }

        return $this->succeeded('applicable linked-to types', data: ['data' => $types]);
    }
}
