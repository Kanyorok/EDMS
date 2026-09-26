<?php

namespace App\Http\Controllers\DMS\Files;

use App\Http\Controllers\Controller;
use App\Models\DMS\DMSFolio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live search behind the upload form's "Existing case" folio picker - the
 * spec requires Folio to be a lookup or system-generated, never free text.
 * Same select2-shaped response as DocumentRelationOptionsController.
 */
class FolioOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = trim($validated['q']);

        $options = DMSFolio::query()
            ->where(fn ($q) => $q->where('Code', 'like', "%{$search}%")
                ->orWhere('Name', 'like', "%{$search}%"))
            ->whereNull('DeletedOn')
            ->limit(15)->get(['Id', 'Code', 'Name'])
            ->map(fn (DMSFolio $folio) => [
                'id' => (string) $folio->Id,
                'text' => $folio->Name ? $folio->Code . ' - ' . $folio->Name : $folio->Code,
            ]);

        return response()->json(['results' => $options->values()]);
    }
}
