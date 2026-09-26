<?php

namespace App\Http\Middleware;

use App\Models\DMS\SourceSystem;
use App\Services\DMS\ExternalDocumentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the {system} route segment to an active source system, and {documentId} (when
 * present) to a document that system filed - before any form request runs, so those can
 * rely on both. A document filed by another system is reported as not found, not forbidden,
 * so one system cannot probe for another's documents.
 */
class ResolveExternalSourceSystem
{
    public function __construct(private readonly ExternalDocumentService $documents)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Set by AuthenticateExternalSource when it runs first; looked up here otherwise.
        $system = $request->attributes->get('sourceSystem')
            ?? SourceSystem::query()
                ->where('Code', (string) $request->route('system'))
                ->where('IsActive', true)
                ->first();

        if (! $system instanceof SourceSystem) {
            return response()->json(['message' => 'unknown source system'], 404);
        }

        $request->attributes->set('sourceSystem', $system);

        if ($request->route('documentId') !== null) {
            $document = $this->documents->find($system, (string) $request->route('documentId'));
            if ($document === null) {
                return response()->json(['message' => 'document not found'], 404);
            }

            $request->attributes->set('document', $document);
        }

        return $next($request);
    }
}
