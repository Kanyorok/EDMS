<?php

namespace App\Http\Controllers\DMS\Files;

use App\Enums\DMS\DocumentCheckOutStatusEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\UploadDocumentRequest;
use App\Http\Resources\DMS\FileResource;
use App\Http\Resources\DMS\FilesCollection;
use App\Models\DMS\Document;
use App\Models\DMS\Repository;
use App\Services\DMS\DocumentService;
use App\Services\DMS\RepositoryService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class DocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->except('show');
        $this->authorizeResource(Document::class);
    }

    /**
     * Display a listing of the resource.
     *
     * Optional filters mirror the metadata a document type drives at upload
     * time - LinkedToTypeId, TagId (record classification), an ExpiryDate
     * range, and the EntityApplicability attribute copied onto each document.
     */
    public function index(Request $request, Repository $repository): FilesCollection
    {
        $query = $repository->documents()->user(auth()->user())->whereHas('current')->with(['current']);

        if (! $request->boolean('IncludeArchived')) {
            $query->whereNull('ArchivedOn');
        }

        if ($linkedToTypeId = $request->integer('LinkedToTypeId')) {
            $query->where('LinkedToTypeId', $linkedToTypeId);
        }

        if ($tagId = $request->integer('TagId')) {
            $query->whereHas('tags', fn ($q) => $q->where('t_DMSTags.Id', $tagId));
        }

        if ($expiryFrom = $request->string('ExpiryFrom')->trim()->toString()) {
            $query->whereDate('ExpiryDate', '>=', $expiryFrom);
        }

        if ($expiryTo = $request->string('ExpiryTo')->trim()->toString()) {
            $query->whereDate('ExpiryDate', '<=', $expiryTo);
        }

        if ($entity = $request->string('EntityApplicability')->trim()->toString()) {
            $query->whereHas('properties', fn ($q) => $q->where('Name', 'EntityApplicability')->where('Value', $entity));
        }

        return new FilesCollection($query->latest('t_Documents.ModifiedOn')->paginate(50));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(UploadDocumentRequest $request, Repository $repository): JsonResponse
    {
        $validated = $request->validated();

        try {
            return $this->succeeded('document uploaded successfully', data: [
                'data' => new FileResource(DocumentService::createUpload($repository, $request->file('file'), $request->user(), uploadRules: [
                    'DocumentTypeId' => $validated['DocumentTypeId'] ?? null,
                    'LinkedToTypeId' => $validated['LinkedToTypeId'] ?? null,
                    'Related' => $validated['Related'] ?? null,
                    'LinkedId' => $validated['LinkedId'] ?? null,
                    'FolioId' => $validated['FolioId'] ?? null,
                    'Notes' => $validated['Notes'] ?? null,
                    'ExpiryDate' => $validated['ExpiryDate'] ?? null,
                ])->document),
            ]);
        } catch (ErroredException $e) {
            return $e->toJson();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Repository $repository, Document $document): View
    {
        $actor = $request->user();
        $document->loadCount('versions')->load([
            'current', 'repository', 'creator', 'category', 'properties',
            'validations.workflowHistory.creator',
            'validations.workflowHistory.status',
            'validations.approver',
            'validations.rejector',
        ]);

        $lock = Cache::lock('view-document-' . $document->DocumentId, 100);
        if ($lock->get()) {
            activity()->causedBy($actor)->performedOn($document)->event('view')->log('viewed document  ' . $document->Name . '.');
        }


        //checked out.
        $service = new DocumentService($document);
        $legalHold = $service->isHold();
        $underReview = $service->isUnderReview();
        $pendingValidation = $service->isPendingValidation();
        $validated = $service->isValidated();
        $checkedOut = ($legalHold || $underReview) ? 0 : $service->isCheckedOut($actor);
        $pendingReview = $service->pendingReview($actor);

        // Checked out (1) but not by me (2): surface who holds it instead of offering a fresh
        // "check out" button, which would read as available when it is not.
        $checkedOutBy = $checkedOut === 1
            ? $document->checkouts()->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)->with('creator')->latest('CreatedOn')->first()?->creator?->Name
            : null;

        return view('dms.files.show')
            ->with('repoService', new RepositoryService($repository))
            ->with('tags', $service->tags($actor)->get())
            ->with('file', $document)
            ->with('legalHold', $legalHold)
            ->with('underReview', $underReview)
            ->with('pendingValidation', $pendingValidation)
            ->with('validated', $validated)
            ->with('pendingReview', $pendingReview)
            ->with('checkIn', ($checkedOut === 2))
            ->with('checkedOut', ($checkedOut !== 0))
            ->with('checkedOutBy', $checkedOutBy);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Repository $repository, Document $document)
    {
        if ($document->RepositoryId !== $repository->Id) {
            return $this->errored('file not found');
        }

        return view('dms.files.summary')
            ->with('file', $document)->with('service', new DocumentService($document));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Repository $repository, Document $document)
    {
        if ($document->RepositoryId !== $repository->Id) {
            return $this->errored('file not found');
        }

        $data = $request->validate([
            'Name' => ['required', 'string', 'min:2', 'max:200'],
        ]);
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($document, $repository, $data, $actor) {
                activity()->causedBy($actor)->performedOn($document)->event('update')->log('rename document  ' . $document->Name . ' to ' . $data['Name'] . '.');

                $document->forceFill([
                    'Name' => $data['Name'] . '.' . pathinfo($document->Name, PATHINFO_EXTENSION),
                    'ModifiedBy' => $actor->Id,
                ])->save();

                return $this->succeeded('document renamed successfully', route('files.show', [$repository->RepositoryId, $document->DocumentId]));
            });
        } catch (Throwable | Exception $e) {
            Log::error('rename file failed : ' . $e);
        }

        return $this->errored('rename file failed, try again later');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Repository $repository, Document $document)
    {
        if ($document->RepositoryId !== $repository->Id) {
            return $this->errored('file not found');
        }
        $service = new DocumentService($document);
        if ($service->isHold()) {
            return $this->errored('document is on legal hold');
        }

        if ($service->isUnderReview()) {
            return $this->errored('document is under review and cannot be deleted');
        }

        if ($service->isPendingValidation()) {
            return $this->errored('document has a pending validation and cannot be deleted');
        }

        if ($service->isCheckedOut()) {
            return $this->errored('document is checked out');
        }

        try {
            return DB::transaction(function () use ($repository, $document) {
                $document->forceFill([
                    'DeletedOn' => now(),
                    'DeletedBy' => auth()->user()->Id,
                ])->save();

                activity()->causedBy(auth()->user())->performedOn($document)->event('delete')->log('trashed document  ' . $document->Name . '.');

                return $this->succeeded(
                    message: 'document trashed successfully',
                    route: route('repo.show', [$repository->RepositoryId]),
                    data: ['data' => new FileResource($document)]
                );
            });
        } catch (Throwable | Exception $e) {
            Log::error('deleting file failed :');
            Log::error($e);
        }

        return $this->errored('an unexpected error occurred');
    }
}
