<?php

namespace App\Http\Controllers\DMS;

use App\Enums\Core\PermissionEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\UploadPhysicalDocumentSoftCopyRequest;
use App\Models\Auth\User;
use App\Models\DMS\PhysicalDocument;
use App\Models\DMS\PhysicalDocumentRelation;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\PhysicalFileMovement;
use App\Models\DMS\Registry;
use App\Models\DMS\Repository;
use App\Models\DMS\StorageLocation;
use App\Models\HRM\Employee;
use App\Services\DMS\DocumentService;
use App\Services\DMS\PhysicalFileService;
use App\Services\DMS\RegistryAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class PhysicalFileController extends Controller
{
    private const array RELATION_TYPES = [
        'hr_employees' => 'Employee',
    ];

    public function __construct(
        private readonly RegistryAccessService $access,
        private readonly PhysicalFileService $physicalFiles
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $registry = $this->registry($request);
            if (! $registry) {
                return DataTables::of(PhysicalFile::query()->whereRaw('1 = 0'))
                    ->addIndexColumn()->make();
            }

            $canManage = $this->canManageFiles($request->user());

            $query = PhysicalFile::query()
                ->with(['currentLocation', 'currentHolder', 'status'])
                ->withCount('documents')
                ->where('HomeRegistryId', $registry->Id)
                ->when(! $canManage, fn ($q) => $q->user($request->user()));

            $this->applyFilters($query, $request);

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('CurrentCustodian', function (PhysicalFile $file) {
                    if ($file->currentHolder) {
                        return e($file->currentHolder->Name);
                    }

                    return e(trim((string) $file->currentLocation?->Path, '/') ?: 'In transit');
                })
                ->addColumn('Status', fn (PhysicalFile $file) => e($file->status?->Description ?: '-'))
                ->editColumn('Visibility', fn (PhysicalFile $file) => $file->Visibility->value === 'pub'
                    ? 'Public'
                    : 'Private')
                ->addColumn('action', function (PhysicalFile $file) use ($canManage) {
                    $label = e($file->FileNumber . ' - ' . $file->Title);

                    // Everyone who can see the row can open it; only a manager
                    // can file more paper into it.
                    $buttons = '<a href="' . route('registry.physical-files.show', $file->Id) . '" '
                        . 'class="btn btn-sm btn-outline-secondary" '
                        . 'title="Open this file"><i class="fas fa-eye"></i></a>';

                    // Paper can only go into a file the registry is holding, so
                    // a file that is out on loan offers no way to file into it.
                    if ($canManage && $this->physicalFiles->isHeldByRegistry($file)) {
                        $buttons .= ' <button type="button" class="btn btn-sm btn-outline-primary add-file-document" '
                            . 'data-id="' . $file->Id . '" data-file="' . $label . '" '
                            . 'title="File another document into this file">'
                            . '<i class="fas fa-circle-plus"></i></button>';
                    }

                    return '<div class="text-nowrap">' . $buttons . '</div>';
                })
                ->rawColumns(['action'])
                ->make();
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) physical files failed : ' . $e);

            return $this->errored('fetching physical files failed, try again later');
        }
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($this->canManageFiles($request->user()), 403);

        $validated = $request->validate([
            'registry' => ['required', 'integer', 'exists:t_DMSRegistries,Id'],
            'HomeLocationId' => ['required', 'integer', 'exists:t_DMSStorageLocations,Id'],
            'FileNumber' => ['required', 'string', 'max:100'],
            'Title' => ['required', 'string', 'max:255'],
            'Description' => ['nullable', 'string', 'max:1000'],
            'Barcode' => ['nullable', 'string', 'max:100'],
            'Visibility' => ['required', Rule::in(['pub', 'pri'])],
            'Documents' => ['required', 'array', 'min:1'],
            'Documents.*.Title' => ['required', 'string', 'max:255'],
            'Documents.*.Description' => ['required', 'string', 'max:1000'],
            'Documents.*.DocumentDate' => ['nullable', 'date'],
            'Documents.*.Relations' => ['nullable', 'array'],
            'Documents.*.Relations.*.Related' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'Documents.*.Relations.*.RelatedID' => ['required', 'string', 'max:100'],
        ]);

        try {
            $registry = $this->registry($request);
            if (! $registry) {
                throw new ErroredException('Select a registry first.');
            }

            $home = StorageLocation::query()
                ->where('RegistryId', $registry->Id)
                ->whereNull('DeletedOn')
                ->find($validated['HomeLocationId']);
            if (! $home) {
                throw new ErroredException('The selected home location is not in this registry.');
            }

            $file = $this->physicalFiles->register(
                $validated,
                $home,
                $request->user(),
                $validated['Documents']
            );

            activity()->causedBy($request->user())->performedOn($file)
                ->event('create')->log('registered physical file ' . $file->FileNumber);

            return $this->succeeded('physical file registered successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('creating (DMS) physical file failed : ' . $e);

            return $this->errored('creating the physical file failed, try again later');
        }
    }

    /**
     * The four filters the files tab offers, in place of a single search box.
     *
     * They are deliberately separate: a registry clerk looks for a file by its
     * number or barcode, by what the file is called, by the title of a paper
     * inside it, or by the client, lead or employee a paper was filed against.
     *
     * @param  Builder<PhysicalFile>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $filter = fn (string $key) => trim((string) $request->get($key, ''));

        $fileNumber = $filter('fileNumber');
        $title = $filter('title');
        $documentTitle = $filter('documentTitle');
        $relation = $filter('documentRelation');

        $query
            // One box for both, because a scanned barcode and a typed file
            // number are the same question asked two ways.
            ->when($fileNumber !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('FileNumber', 'like', "%{$fileNumber}%")
                ->orWhere('Barcode', 'like', "%{$fileNumber}%")))
            ->when($title !== '', fn (Builder $q) => $q->where('Title', 'like', "%{$title}%"))
            ->when($documentTitle !== '', fn (Builder $q) => $q->whereHas(
                'documents',
                fn (Builder $d) => $d->where('Title', 'like', "%{$documentTitle}%")
            ))
            ->when($relation !== '', fn (Builder $q) => $q->whereHas(
                'documents.relations',
                fn (Builder $r) => $this->matchRelation($r, $relation)
            ));
    }

    /**
     * Matches a linked record by its identifier or by its name.
     *
     * The link is a morph, so each type is named by its own columns. The
     * builder arrives from whereHas() untyped, which is why it is not narrowed.
     */
    private function matchRelation(Builder $query, string $term): void
    {
        $query->where('RelatedID', 'like', "%{$term}%")
            ->orWhereHasMorph(
                'related',
                array_keys(self::RELATION_TYPES),
                function (Builder $related, string $type) use ($term) {
                    match ($type) {
                        'hr_employees' => $related->where(fn (Builder $q) => $q
                            ->where('EmployeeID', 'like', "%{$term}%")
                            ->orWhere('FirstName', 'like', "%{$term}%")
                            ->orWhere('LastName', 'like', "%{$term}%")),
                        default => $related->whereRaw('1 = 0'),
                    };
                }
            );
    }

    /**
     * The whole record of one physical file, on a page of its own.
     *
     * Everything a custodian needs to manage the file sits here: what it holds,
     * who has asked for it, and where it has been. Where a paper has a
     * counterpart in t_Documents the soft copy is offered for preview - that
     * link is the only thing tying the shelf to the repository.
     */
    public function show(Request $request, PhysicalFile $physicalFile): View
    {
        $this->authorize('viewAny', Registry::class);

        $user = $request->user();
        $canManage = $this->canManageFiles($user);

        // Anyone who can see the file in the list can open it.
        abort_unless($canManage || $this->physicalFiles->canSee($physicalFile, $user), 403);

        try {
            $registry = $this->assertFileInBranch($physicalFile, $request);
        } catch (ErroredException $e) {
            abort(403, $e->getMessage());
        }

        $canDecide = $this->physicalFiles->canApprove($registry, $user);
        $canOperate = $canManage || $canDecide;

        $physicalFile->loadMissing([
            'homeLocation', 'currentLocation', 'currentRegistry', 'currentHolder', 'status',
            'activeCheckout.borrower',
        ])->loadCount('documents');

        return view('dms.registry.files.show', [
            'file' => $physicalFile,
            'registry' => $registry,
            'canManage' => $canManage,
            // Filing needs the folder in hand, which a lent-out file is not.
            'canFileDocuments' => $canManage && $this->physicalFiles->isHeldByRegistry($physicalFile),
            'canDecide' => $canDecide,
            'canOperate' => $canOperate,
            // Sharing is a grant on the file itself, not a registry right.
            'canShare' => $this->physicalFiles->canShare($physicalFile, $user),
            // Somewhere to file a scan. Only what this user may reach.
            'repositories' => $canManage
                ? Repository::query()->user($user)->orderBy('Name')->get(['Id', 'Name'])
                : collect(),
            // A requester only ever sees their own asks, the same way the
            // registry workspace scopes its requests table.
            'requests' => $physicalFile->requests()
                ->with(['requester', 'status', 'decidedBy'])
                ->when(! $canOperate, fn (Builder $q) => $q->where('RequestedByUserId', $user->Id))
                ->whereNull('DeletedOn')
                ->orderByDesc('NeededFrom')
                ->get(),
        ]);
    }

    /**
     * The documents pane of a file's page, fetched after the page.
     *
     * Rendered server-side because each row needs a preview URL, a policy check
     * on the soft copy, and - where there is none yet - the upload affordance.
     */
    public function documentsPanel(Request $request, PhysicalFile $physicalFile): View
    {
        $this->authorize('viewAny', Registry::class);

        $user = $request->user();
        $canManage = $this->canManageFiles($user);

        abort_unless($canManage || $this->physicalFiles->canSee($physicalFile, $user), 403);

        try {
            $this->assertFileInBranch($physicalFile, $request);
        } catch (ErroredException $e) {
            abort(403, $e->getMessage());
        }

        $documents = $physicalFile->documents()
            ->with(['digitalDocument', 'relations.related'])
            ->whereNull('DeletedOn')
            ->orderBy('SortOrder')
            ->get();

        return view('dms.registry.files._documents', [
            'canManage' => $canManage,
            'documents' => $documents->map(fn (PhysicalDocument $document) => [
                'id' => $document->Id,
                'title' => $document->Title,
                'reference' => $document->ReferenceNumber,
                // Older databases kept this text in Notes; the repair migration
                // moves it to Description where it can.
                'description' => $document->Description ?: $document->getAttribute('Notes'),
                'date' => $document->DocumentDate?->format('d M Y'),
                'softCopy' => $document->digitalDocument,
                'links' => $this->documentLinks($document),
            ])->values(),
        ]);
    }

    /**
     * Files a scan against a paper document that has none yet.
     *
     * The upload itself goes through the DMS the same way any other document
     * does; all this adds is the link back to the paper on the shelf.
     */
    public function uploadSoftCopy(
        UploadPhysicalDocumentSoftCopyRequest $request,
        PhysicalDocument $physicalDocument
    ): JsonResponse {
        $this->authorize('viewAny', Registry::class);
        abort_unless($this->canManageFiles($request->user()), 403);

        try {
            $file = $physicalDocument->physicalFile()->firstOrFail();
            $this->assertFileInBranch($file, $request);

            if ($physicalDocument->DocumentId !== null) {
                throw new ErroredException(
                    'This document already has a soft copy. Upload a new version from the document itself.'
                );
            }

            // Only a repository this user may actually file into.
            $repository = Repository::query()
                ->user($request->user())
                ->whereKey($request->integer('repository'))
                ->first();

            if (! $repository) {
                throw new ErroredException('Choose a repository you have access to.');
            }

            $document = DocumentService::createUpload(
                $repository,
                $request->file('file'),
                $request->user()
            )->document;

            $physicalDocument->DocumentId = $document->Id;
            $physicalDocument->ModifiedBy = $request->user()->Id;
            $physicalDocument->save();

            // Logged against the file so it shows in the file's own history.
            activity()->causedBy($request->user())->performedOn($file)
                ->event('update')
                ->log('linked soft copy ' . $document->Name . ' to document ' . $physicalDocument->Title);

            return $this->succeeded('soft copy uploaded and linked successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('linking a soft copy to a (DMS) physical document failed : ' . $e);

            return $this->errored('uploading the soft copy failed, try again later');
        }
    }

    /**
     * The records a paper document was filed against, named for display.
     *
     * @return array<int,string>
     */
    private function documentLinks(PhysicalDocument $document): array
    {
        // getRelation() rather than ->relations: that name is also Eloquent's
        // own property on every model.
        return $document->getRelation('relations')
            ->map(function (PhysicalDocumentRelation $relation) {
                $related = $relation->related;

                $name = match (true) {
                    $related instanceof Employee => trim($related->FirstName . ' ' . $related->LastName),
                    default => null,
                };

                return (self::RELATION_TYPES[$relation->Related] ?? $relation->Related)
                    . ': ' . ($name ?: $relation->RelatedID);
            })
            ->all();
    }

    /**
     * What is already filed in one physical file.
     *
     * Shown when adding a further document, so the clerk can see what the file
     * already holds before filing another paper into it.
     */
    public function documents(Request $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);
        abort_unless($this->canManageFiles($request->user()), 403);

        try {
            $this->assertFileInBranch($physicalFile, $request);

            // Counted rather than loaded: PhysicalDocument::relations() shares a
            // name with Eloquent's own $relations, so the loaded relation is
            // best left unread.
            $documents = $physicalFile->documents()
                ->withCount('relations')
                ->whereNull('DeletedOn')
                ->orderBy('SortOrder')
                ->get();

            return response()->json([
                'file' => [
                    'id' => $physicalFile->Id,
                    'fileNumber' => $physicalFile->FileNumber,
                    'title' => $physicalFile->Title,
                ],
                'documents' => $documents->map(fn (PhysicalDocument $document) => [
                    'id' => $document->Id,
                    'title' => $document->Title,
                    // Older databases kept this text in Notes; the repair
                    // migration moves it to Description where it can.
                    'description' => $document->Description ?: $document->getAttribute('Notes'),
                    'documentDate' => $document->DocumentDate?->format('d M Y'),
                    'relations' => (int) $document->relations_count,
                ])->values(),
            ]);
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) physical file documents failed : ' . $e);

            return $this->errored('fetching the file contents failed, try again later');
        }
    }

    /**
     * Files one or more further paper documents into an existing file.
     */
    public function storeDocuments(Request $request, PhysicalFile $physicalFile): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);
        abort_unless($this->canManageFiles($request->user()), 403);

        $validated = $request->validate([
            'Documents' => ['required', 'array', 'min:1'],
            'Documents.*.Title' => ['required', 'string', 'max:255'],
            'Documents.*.Description' => ['required', 'string', 'max:1000'],
            'Documents.*.DocumentDate' => ['nullable', 'date'],
            'Documents.*.Relations' => ['nullable', 'array'],
            'Documents.*.Relations.*.Related' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'Documents.*.Relations.*.RelatedID' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->assertFileInBranch($physicalFile, $request);

            $added = $this->physicalFiles->addDocuments(
                $physicalFile,
                $validated['Documents'],
                $request->user()
            );

            activity()->causedBy($request->user())->performedOn($physicalFile)
                ->event('update')
                ->log('filed ' . $added->count() . ' document(s) into physical file ' . $physicalFile->FileNumber);

            return $this->succeeded(
                $added->count() . ' document(s) filed into ' . $physicalFile->FileNumber
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('adding documents to a (DMS) physical file failed : ' . $e);

            return $this->errored('filing the document failed, try again later');
        }
    }

    public function receive(Request $request, PhysicalFileMovement $physicalFileMovement): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $registry = $this->registry($request);
            if (! $registry || (int) $physicalFileMovement->ToRegistryId !== (int) $registry->Id) {
                throw new ErroredException('This movement is not addressed to the selected registry.');
            }

            $file = $this->physicalFiles->receive($physicalFileMovement, $request->user());

            activity()->causedBy($request->user())->performedOn($file)
                ->event('receive')->log('received physical file ' . $file->FileNumber);

            return $this->succeeded('physical file received successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('receiving (DMS) physical file failed : ' . $e);

            return $this->errored('receiving the physical file failed, try again later');
        }
    }

    public function relationOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);
        abort_unless($this->canManageFiles($request->user()), 403);

        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::RELATION_TYPES))],
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);
        $search = trim($validated['q']);

        $options = match ($validated['type']) {
            'hr_employees' => Employee::query()
                ->where(fn ($q) => $q->where('EmployeeID', 'like', "%{$search}%")
                    ->orWhere('FirstName', 'like', "%{$search}%")
                    ->orWhere('LastName', 'like', "%{$search}%"))
                ->limit(15)->get(['Id', 'EmployeeID', 'FirstName', 'LastName'])
                ->map(fn ($item) => [
                    'id' => (string) $item->Id,
                    'text' => $item->EmployeeID . ' - ' . trim($item->FirstName . ' ' . $item->LastName),
                ]),
        };

        return response()->json(['results' => $options->values()]);
    }

    /**
     * Shelving and file registration are one permission, per
     * StorageLocationPolicy - filing paper into a file is the same job.
     */
    private function canManageFiles(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value)
            || $user->can(PermissionEnum::Admin->value);
    }

    /**
     * A file is reachable only from the branch that custodies it.
     *
     * @throws ErroredException
     */
    private function assertFileInBranch(PhysicalFile $file, Request $request): Registry
    {
        $registry = $file->homeRegistry()->firstOrFail();

        $this->access->assertAccessible($request->user(), $registry);

        return $registry;
    }

    private function registry(Request $request): ?Registry
    {
        if (! $request->filled('registry')) {
            return $this->access->defaultRegistry($request->user());
        }

        $registry = Registry::query()->whereNull('DeletedOn')->find($request->integer('registry'));
        if (! $registry) {
            throw new ErroredException('That registry no longer exists.');
        }

        $this->access->assertAccessible($request->user(), $registry);

        return $registry;
    }
}
