<?php

namespace App\Http\Controllers\API\DMS;

use App\Enums\Core\ModulesEnum;
use App\Enums\Core\RoleEnum;
use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\UploadDocumentRequest;
use App\Models\Auth\User;
use App\Models\DMS\Document;
use App\Services\DMS\DocumentService;
use App\Services\DMS\RepositoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class DocumentApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $actor = $request->user();
            $q = trim((string)$request->query('q', ''));
            $page = max(1, (int)$request->query('page', 1));
            $limit = max(1, min(50, (int)$request->query('limit', 20)));

            $query = Document::query()
                ->whereHas('current')
                ->with(['current', 'repository'])
                ->orderByDesc('ModifiedOn');

            $onlyMine = $request->boolean('my');
            $query->user($actor);
            if ($onlyMine) {
                $query->where('t_Documents.CreatedBy', $actor->Id);
            }

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('t_Documents.Name', 'like', "%{$q}%")
                        ->orWhereHas('current', function ($cw) use ($q) {
                            $cw->where('t_DocumentVersions.Name', 'like', "%{$q}%");
                        });
                });
            }

            $total = (clone $query)->count();
            $items = $query->forPage($page, $limit)->get();

            $data = $items->map(function (Document $doc) {
                return [
                    'id' => $doc->Id,
                    'name' => $doc->Name,
                    'repository' => $doc->repository?->Name,
                    'visibility' => $doc->Visibility?->value,
                    'size' => $doc->current?->Size,
                    'version' => $doc->current?->Version,
                    'mimeType' => $doc->ext()?->getMimeType(),
                    'createdOn' => optional($doc->CreatedOn)?->toIso8601String(),
                    'modifiedOn' => optional($doc->ModifiedOn)?->toIso8601String(),
                    // Build a predictable path without requiring route registration
                    'previewUrl' => url('/dms/document/' . $doc->Id . '/preview'),
                ];
            });

            return response()->json([
                'data' => $data,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int)ceil($total / $limit),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to list documents',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generic document upload, used by the "Upload & documents" dashboard page.
     * Files are stored as real DMS documents (t_Documents / t_DocumentVersions),
     * not a side table, so they show up through index() / the DMS module too.
     */
    public function store(UploadDocumentRequest $request): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            return response()->json(['error' => 'Only internal users can upload documents here'], 403);
        }

        try {
            $repository = RepositoryService::module(ModulesEnum::DMS);

            $service = DocumentService::createUpload($repository, $request->file('file'), $actor)
                ->addPermission($actor, RoleEnum::Admin, SystemHelper::user(), false);

            $document = $service->document;

            return response()->json([
                'data' => [
                    'id' => $document->Id,
                    'documentId' => $document->DocumentId,
                    'name' => $document->Name,
                    'size' => $document->current?->Size,
                    'mimeType' => $document->ext()?->getMimeType(),
                ],
            ], 201);
        } catch (ErroredException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('DMS generic document upload failed: ' . $e);

            return response()->json(['error' => 'Failed to upload document'], 500);
        }
    }

    /**
     * Streams the document's raw bytes (inline for previewable types, attachment otherwise)
     * instead of embedding the whole file as base64 inside an HTML page - avoids the 33%
     * base64 bloat and the giant string being built server-side, which is what made large
     * PDFs slow enough to trip client-side timeouts.
     */
    public function preview(Request $request): Response|JsonResponse
    {
        $documentId = $request->query('documentId') ?? $request->query('id') ?? $request->query('document_id');

        try {
            $document = Document::query()->where(function ($query) use ($documentId) {
                $query->where('DocumentId', $documentId);
                if (is_numeric($documentId)) {
                    $query->orWhere('Id', $documentId);
                }
            })->first();

            if (! $document instanceof Document) {
                return response()->json(['error' => 'Document not found'], 404);
            }

            $actor = $request->user();
            $this->authorize('view', $document);

            $service = new DocumentService($document);
            $content = $service->getFileContent(false);
            $disposition = $service->isPrevieable() ? 'inline' : 'attachment';

            return response($content, 200)
                ->header('Content-Type', $service->type->getMimeType())
                ->header('Content-Disposition', $disposition . '; filename="' . addslashes($document->Name) . '"');
        } catch (ErroredException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
            return response()->json(['error' => 'Not permitted'], 403);
        } catch (Throwable $e) {
            Log::error('DMS preview failed: ' . $e);

            return response()->json(['error' => 'Failed to load document'], 500);
        }
    }
}
