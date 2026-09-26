<?php

namespace App\Http\Controllers\DMS\API;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\ExternalDocumentReplaceRequest;
use App\Http\Requests\DMS\ExternalDocumentUploadRequest;
use App\Models\DMS\Document;
use App\Models\DMS\SourceSystem;
use App\Services\DMS\DocumentService;
use App\Services\DMS\EventCatalogService;
use App\Services\DMS\EventPropertyValidator;
use App\Services\DMS\ExternalDocumentService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The DMS side of an external system's document handling (core banking, through the
 * gateway). {system} and {documentId} are resolved by ResolveExternalSourceSystem, so a
 * system can only reach its own events and its own documents.
 *
 * Failures a caller can act on are 4xx with a "message": 404 unknown system/document,
 * 409 the document is locked (with a stable "reason": legal_hold, under_review, archived,
 * checked_out, pending_validation), 422 the request or file failed validation.
 */
class ExternalDocumentController extends Controller
{
    public function __construct(private readonly ExternalDocumentService $documents)
    {
    }

    /** What may be uploaded from a screen: events, document type rules and the form definition. */
    public function events(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module' => ['required', 'integer', 'min:1'],
            'entityType' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json(['data' => app(EventCatalogService::class)->forModule(
            $this->system($request), (int) $data['module'], $data['entityType'] ?? null
        )]);
    }

    public function store(ExternalDocumentUploadRequest $request): JsonResponse
    {
        $system = $this->system($request);
        $event = $request->resolvedEvent();

        // Same definition the catalog handed out: the form is not trusted to have enforced it.
        $properties = app(EventPropertyValidator::class)->validate($event, (array) $request->input('properties', []));

        try {
            $document = $this->documents->ingest(
                $system,
                $event,
                $request->resolvedDocumentType(),
                (int) $request->input('module'),
                (string) $request->input('entityId'),
                $request->file('file'),
                (string) $request->input('actor'),
                $request->input('actorName'),
                $properties,
                $request->filled('expiryDate') ? Carbon::parse($request->input('expiryDate')) : null,
                $request->input('notes'),
            );

            return response()->json(['data' => $this->documents->present($document->load(['current', 'eventType', 'documentType', 'properties']))], 201);
        } catch (ErroredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('external document upload failed: ' . $e);

            return response()->json(['message' => 'failed to store the document'], 500);
        }
    }

    /** Documents filed against one external record. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entityType' => ['required', 'string', 'max:50'],
            'entityId' => ['required', 'string', 'max:100'],
            'event' => ['nullable', 'string', 'max:50'],
            'module' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $documents = $this->documents->forEntity(
                $this->system($request), $data['entityType'], $data['entityId'], $data['event'] ?? null, isset($data['module']) ? (int) $data['module'] : null
            );

            return response()->json(['data' => $documents->map(fn (Document $d) => $this->documents->present($d))->values()]);
        } catch (Throwable $e) {
            Log::error('external document list failed: ' . $e);

            return response()->json(['message' => 'failed to load documents'], 500);
        }
    }

    public function show(Request $request): JsonResponse
    {
        try {
            return response()->json(['data' => $this->documents->present($this->document($request))]);
        } catch (Throwable $e) {
            Log::error('external document read failed: ' . $e);

            return response()->json(['message' => 'failed to load the document'], 500);
        }
    }

    /** A short-lived link to the current version. Permission is decided here, not by whoever holds the link later. */
    public function link(Request $request): JsonResponse
    {
        $data = $request->validate(['actor' => ['required', 'string', 'max:100']]);

        return response()->json(['data' => $this->documents->contentLink($this->document($request), $data['actor'])]);
    }

    public function replace(ExternalDocumentReplaceRequest $request): JsonResponse
    {
        $system = $this->system($request);
        $document = $this->document($request);

        try {
            $reason = (new DocumentService($document))->lockReason();
            if ($reason !== null) {
                return response()->json(['message' => 'document cannot be changed: ' . str_replace('_', ' ', $reason) . '.', 'reason' => $reason], 409);
            }

            $updated = $this->documents->replace(
                $system, $document, $request->file('file'), (string) $request->input('actor'), $request->input('actorName'), $request->input('remark')
            );

            return response()->json(['data' => $this->documents->present($updated->load(['current', 'eventType', 'documentType', 'properties']))]);
        } catch (ErroredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('external document replace failed: ' . $e);

            return response()->json(['message' => 'failed to replace the document'], 500);
        }
    }

    /**
     * Ask for a review of the current version. "reviewer" (a DMS user ID) overrides the event's
     * default for this one request.
     */
    public function review(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actor' => ['required', 'string', 'max:100'],
            'actorName' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
            'reviewer' => ['nullable', 'string', 'max:100'],
        ]);
        $document = $this->document($request);

        return $this->requested($document, function () use ($request, $document, $data) {
            $this->documents->requestReview($this->system($request), $document, $data['actor'], $data['actorName'] ?? null, $data['note'] ?? null, $data['reviewer'] ?? null);
        }, 'review');
    }

    /** Put the current version through the event's validation type. */
    public function validation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'actor' => ['required', 'string', 'max:100'],
            'actorName' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        $document = $this->document($request);

        return $this->requested($document, function () use ($request, $document, $data) {
            $this->documents->requestValidation($this->system($request), $document, $data['actor'], $data['actorName'] ?? null, $data['note'] ?? null);
        }, 'validation');
    }

    /**
     * Shared shape of the review and validation requests: refused with 409 and a reason while the
     * document is locked (a legal hold above all), 422 when it cannot be routed, otherwise 201
     * with the document as it now stands.
     */
    private function requested(Document $document, callable $action, string $what): JsonResponse
    {
        try {
            $reason = (new DocumentService($document))->lockReason();
            if ($reason !== null) {
                return response()->json(['message' => 'document cannot be changed: ' . str_replace('_', ' ', $reason) . '.', 'reason' => $reason], 409);
            }

            $action();

            return response()->json(['data' => $this->documents->present($document->fresh(['current', 'eventType', 'documentType', 'properties']))], 201);
        } catch (ErroredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("external document {$what} request failed: " . $e);

            return response()->json(['message' => "failed to request the {$what}"], 500);
        }
    }

    /**
     * Streams the current version. Reached only through a signed link (see link()), which
     * expires in minutes and carries the actor the view is logged against.
     */
    public function content(Request $request, string $documentId): Response|JsonResponse
    {
        $document = Document::query()
            ->whereNotNull('SourceSystemId')
            ->where('DocumentId', $documentId)
            ->first();

        if (! $document instanceof Document) {
            return response()->json(['message' => 'document not found'], 404);
        }

        try {
            $service = new DocumentService($document);
            $content = $service->getFileContent(false);

            activity()->causedBy($document->sourceSystem->serviceUser)->performedOn($document)->event('view')
                ->log('Viewed from ' . $document->sourceSystem->Code . ' by ' . $request->query('actor'));

            return response($content, 200)
                ->header('Content-Type', $service->type->getMimeType())
                ->header('Content-Disposition', ($service->isPrevieable() ? 'inline' : 'attachment') . '; filename="' . addslashes($document->Name) . '"')
                ->header('Cache-Control', 'private, no-store');
        } catch (ErroredException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::error('external document content failed: ' . $e);

            return response()->json(['message' => 'failed to load the document'], 500);
        }
    }

    private function system(Request $request): SourceSystem
    {
        return $request->attributes->get('sourceSystem');
    }

    private function document(Request $request): Document
    {
        return $request->attributes->get('document');
    }
}
