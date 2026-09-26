<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\StorageLocationRequest;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\PhysicalFileCheckout;
use App\Models\DMS\PhysicalFileMovement;
use App\Models\DMS\PhysicalFileRequest;
use App\Models\DMS\Registry;
use App\Models\DMS\StorageLocation;
use App\Services\DMS\PhysicalFileService;
use App\Services\DMS\RegistryAccessService;
use App\Services\DMS\RegistryService;
use App\Services\DMS\StorageLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

/**
 * Storage-location CRUD for DMS settings.
 *
 * Two independent gates apply to every action and both must pass:
 *   - the policy, which asks whether the user may manage shelving at all;
 *   - RegistryAccessService, which asks whether this registry belongs to the
 *     branch they are logged into.
 *
 * The registry arrives as a request parameter, so the branch check cannot live
 * in the page load alone - a branch clerk could otherwise reach another
 * branch's shelving by changing the id.
 */
class StorageLocationController extends Controller
{
    public function __construct(
        private readonly StorageLocationService $locations,
        private readonly RegistryAccessService $access,
        private readonly PhysicalFileService $physicalFiles,
        private readonly RegistryService $registries
    ) {
    }

    /**
     * The registry workspace: shelving, custody and requests for one registry.
     *
     * Two audiences share the page and see very different things. A user with
     * no rights over the registry can only ask for a file they are allowed to
     * see and follow their own requests, so everything else - the shelving
     * tree, the incoming tray, the custody dashboards - is neither rendered
     * nor queried for them.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', StorageLocation::class);

        $user = $request->user();

        try {
            $registry = $this->resolveRegistry($request);
        } catch (ErroredException $e) {
            // Out-of-branch registry: refuse rather than silently falling back
            // to the user's own, which would hide the fact it was denied.
            return abort(403, $e->getMessage());
        }

        $registry?->loadMissing(['hod', 'status']);

        $canManageRegistry = $this->canManage($user);
        $canOperateRegistry = $registry !== null && $this->canOperate($registry, $user);

        return view('dms.settings.locations.index', [
            'registry' => $registry,
            'registryCount' => $this->access->scope($user)->count(),
            'isHeadOffice' => $this->access->isHeadOffice($user),
            'canManageRegistry' => $canManageRegistry,
            'canOperateRegistry' => $canOperateRegistry,
            // Taking a file back is the registry's own authority, which a
            // global manage right does not confer - so it is asked separately.
            'canCheckIn' => $registry !== null && $this->physicalFiles->canApprove($registry, $user),
            // The permissions tab answers to the global permission right or an
            // Admin grant on the registry - not to running the registry.
            'canManagePermissions' => $registry !== null
                && $this->registries->canManagePermissions($registry, $user),
            'types' => $canManageRegistry ? $this->codes('LocationTypeId') : collect(),
            'purposes' => $canManageRegistry ? $this->codes('LocationPurposeId') : collect(),
            'statuses' => $canManageRegistry ? $this->codes('LocationStatusId') : collect(),
            'requestableFiles' => $registry ? PhysicalFile::query()
                ->user($user)
                ->with('status')
                ->where('HomeRegistryId', $registry->Id)
                ->whereNull('DeletedOn')
                ->whereHas('status', fn ($q) => $q->where('Value', '<>', 'missing'))
                ->orderBy('FileNumber')
                ->get(['Id', 'FileNumber', 'Title', 'FileStatusId']) : collect(),
            'registryStats' => $this->stats($registry, $user, $canOperateRegistry, $canManageRegistry),
            ...($canOperateRegistry
                ? $this->dashboard($registry, $user, $canManageRegistry)
                : $this->emptyDashboard()),
        ]);
    }

    /**
     * The registries this user may work in, for the offcanvas switcher.
     *
     * RegistryAccessService::scope() is what makes this a branch list: a branch
     * user sees their own branch's registries and nothing else, while head
     * office oversees them all.
     */
    public function registries(Request $request): View
    {
        $this->authorize('viewAny', StorageLocation::class);

        return view('dms.settings.locations._registries', [
            'registries' => $this->access->scope($request->user())
                ->with(['hod', 'status'])
                ->withCount(['locations', 'physicalFiles'])
                ->orderBy('Name')
                ->get(),
            'currentId' => $request->integer('current') ?: null,
        ]);
    }

    /**
     * The shelving tree, fetched after the page rather than baked into it.
     *
     * Keeping it on its own endpoint is what lets the panel refresh itself
     * after a write, or on demand, without a page load.
     */
    public function tree(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StorageLocation::class);

        try {
            $registry = $this->resolveRegistry($request);

            if (! $registry) {
                return response()->json([]);
            }

            $user = $request->user();

            // Shelving is an operator's view of the registry; a plain requester
            // has no business reading its structure. Returned rather than
            // aborted so the catch below cannot swallow it into a 400.
            if (! $this->canOperate($registry, $user)) {
                return $this->errored('You cannot view the shelving of this registry.', status: 403);
            }

            return response()->json(
                $this->storageTree($registry, $user, $this->canManage($user))->values()
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) storage locations failed : ' . $e);

            return $this->errored('fetching storage locations failed, try again later');
        }
    }

    /**
     * Parent options for the create/edit form, limited to one registry.
     *
     * Also feeds the physical-file form's home-location list, which needs the
     * same set: every location in the registry, addressed by path.
     */
    public function parents(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StorageLocation::class);

        try {
            $registry = $this->resolveRegistry($request);
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        }

        if (! $registry) {
            return response()->json([]);
        }

        $options = StorageLocation::query()
            ->where('RegistryId', $registry->Id)
            ->whereNull('DeletedOn')
            ->when(
                $request->filled('exclude'),
                fn ($q) => $q->where('Id', '<>', $request->integer('exclude'))
            )
            ->orderBy('Path')
            ->get(['Id', 'Code', 'Label', 'Path'])
            ->map(fn (StorageLocation $l) => [
                'Id' => $l->Id,
                'Label' => trim($l->Path, '/') . ($l->Label ? ' - ' . $l->Label : ''),
            ]);

        return response()->json($options);
    }

    public function store(StorageLocationRequest $request): JsonResponse
    {
        $this->authorize('create', StorageLocation::class);

        try {
            $registry = $this->resolveRegistry($request);

            if (! $registry) {
                return $this->errored('select a registry first');
            }

            $parent = $this->resolveParent($request->integer('ParentId'), $registry);
            $location = $this->locations->create($registry, $parent, $request->validated(), $request->user());

            activity()->causedBy($request->user())->performedOn($location)
                ->event('create')->log('created storage location ' . $location->Path);

            // The node travels back with the response so the tree can grow by
            // one instead of being reloaded.
            return $this->succeeded(
                "location {$location->Code} created successfully",
                data: ['node' => $this->singleNode($location, $registry, $request->user())]
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('creating (DMS) storage location failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(StorageLocationRequest $request, StorageLocation $storageLocation): JsonResponse
    {
        $this->authorize('update', $storageLocation);

        $validated = $request->validated();

        try {
            $registry = $this->registryFor($storageLocation, $request);
            $parentId = $request->integer('ParentId') ?: null;

            // A rename or a move rewrites the Path and Depth of everything
            // beneath this node, so the client cannot patch a single row - it
            // has to refetch the tree.
            $structureChanged = $validated['Code'] !== $storageLocation->Code
                || (int) $storageLocation->ParentId !== (int) $parentId;

            // Code and parent drive Path, so both go through the service; the
            // rest is a plain attribute update.
            if ($validated['Code'] !== $storageLocation->Code) {
                $this->locations->rename($storageLocation, $validated['Code'], $request->user());
            }

            if ((int) $storageLocation->ParentId !== (int) $parentId) {
                $parent = $this->resolveParent($parentId, $registry);
                $this->locations->move($storageLocation->refresh(), $parent, $request->user());
            }

            $storageLocation->refresh()->update([
                'Label' => $validated['Label'] ?? null,
                'LocationTypeId' => $validated['LocationTypeId'],
                'LocationPurposeId' => $validated['LocationPurposeId'],
                'LocationStatusId' => $validated['LocationStatusId'],
                'Barcode' => $validated['Barcode'] ?? null,
                'Capacity' => $validated['Capacity'] ?? null,
                'SortOrder' => $validated['SortOrder'] ?? 0,
                'ModifiedBy' => $request->user()->Id,
            ]);

            activity()->causedBy($request->user())->performedOn($storageLocation)
                ->event('update')->log('updated storage location ' . $storageLocation->Path);

            return $this->succeeded(
                "location {$storageLocation->Code} updated successfully",
                data: [
                    'node' => $this->singleNode($storageLocation->refresh(), $registry, $request->user()),
                    'structureChanged' => $structureChanged,
                ]
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('updating (DMS) storage location failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, StorageLocation $storageLocation): JsonResponse
    {
        $this->authorize('delete', $storageLocation);

        try {
            $this->registryFor($storageLocation, $request);

            $path = $storageLocation->Path;
            $this->locations->delete($storageLocation, $request->user());

            activity()->causedBy($request->user())->performedOn($storageLocation)
                ->event('delete')->log('deleted storage location ' . $path);

            // The service refuses to delete a node that still has children, so
            // dropping this one id from the tree is always enough.
            return $this->succeeded(
                "location {$storageLocation->Code} removed successfully",
                data: ['id' => $storageLocation->Id]
            );
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('deleting (DMS) storage location failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * May the user manage shelving and register files, in any registry?
     *
     * Mirrors StorageLocationPolicy and PhysicalFileController::store.
     */
    private function canManage(User $user): bool
    {
        return $user->can(PermissionEnum::DMSRegistryManage->value)
            || $user->can(PermissionEnum::Admin->value);
    }

    /**
     * May the user work this registry - receive files, decide requests?
     *
     * Managers can, and so can the registry HOD and their delegates.
     */
    private function canOperate(Registry $registry, User $user): bool
    {
        return $this->canManage($user) || $this->physicalFiles->canApprove($registry, $user);
    }

    /**
     * Headline counts for the workspace tabs.
     *
     * A user who cannot operate the registry only ever sees their own
     * requests, so their counts are scoped the same way the tables are. The
     * location count is missing on purpose - the tree reports its own once it
     * has loaded.
     *
     * @return array<string,int>
     */
    private function stats(?Registry $registry, User $user, bool $canOperate, bool $canManage): array
    {
        if (! $registry) {
            return ['files' => 0, 'requests' => 0];
        }

        return [
            'files' => $canOperate
                ? PhysicalFile::query()
                    ->when(! $canManage, fn ($q) => $q->user($user))
                    ->where('HomeRegistryId', $registry->Id)->whereNull('DeletedOn')->count()
                : 0,
            'requests' => PhysicalFileRequest::query()
                ->whereHas('physicalFile', fn ($q) => $q->where('HomeRegistryId', $registry->Id))
                ->when(! $canOperate, fn ($q) => $q->where('RequestedByUserId', $user->Id))
                ->whereNull('DeletedOn')->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function emptyDashboard(): array
    {
        return [
            'incomingMovements' => collect(),
            'onLoanCheckouts' => collect(),
            'overdueCheckouts' => collect(),
            'filesWithIssues' => collect(),
            'recentMovements' => collect(),
            'incomingCounts' => [
                'files' => 0, 'filing' => 0, 'rejected' => 0, 'overdue' => 0, 'issues' => 0,
            ],
        ];
    }

    /**
     * The whole shelving tree of one registry, in the shape the panel consumes.
     *
     * Deletion is decided per node, but resolving it node by node would cost a
     * query each: ask once for the nodes this user administers.
     */
    private function storageTree(Registry $registry, User $user, bool $canManage): Collection
    {
        $files = $this->registryFiles($registry, $user, $canManage);
        $overdue = $this->overdueCheckouts($registry, $user, $canManage);

        // Mirrors StorageLocationPolicy::delete(): a real grant, not the public
        // visibility that merely lets the shelving be seen.
        $deletable = $user->can(PermissionEnum::Admin->value)
            ? null
            : ($canManage
                ? StorageLocation::query()
                    ->userGrant($user, [RoleEnum::Admin->value])
                    ->where('RegistryId', $registry->Id)
                    ->whereNull('DeletedOn')
                    ->pluck('Id')
                    ->map(fn ($id) => (int) $id)
                    ->all()
                : []);

        $locations = StorageLocation::query()
            ->with(['type', 'purpose', 'status', 'parent'])
            ->where('RegistryId', $registry->Id)
            ->whereNull('DeletedOn')
            ->orderBy('Path')
            ->get();

        return $locations->map(function (StorageLocation $location) use (
            $files,
            $overdue,
            $registry,
            $canManage,
            $deletable
        ) {
            $underNode = $files->filter(
                fn (PhysicalFile $file) => $file->currentLocation
                    && str_starts_with((string) $file->currentLocation->Path, (string) $location->Path)
            );

            return $this->node($location, $registry, [
                'directFiles' => $files->filter(
                    fn (PhysicalFile $file) => (int) $file->CurrentLocationId === (int) $location->Id
                )->count(),
                'totalFiles' => $underNode->count(),
                'issues' => $underNode->filter(
                    fn (PhysicalFile $file) => in_array($file->status?->Value, ['missing', 'damaged'], true)
                )->count(),
                'overdue' => $overdue->filter(
                    fn (PhysicalFileCheckout $checkout) => $checkout->physicalFile?->homeLocation
                        && str_starts_with(
                            (string) $checkout->physicalFile->homeLocation->Path,
                            (string) $location->Path
                        )
                )->count(),
            ], $canManage, $deletable === null || in_array((int) $location->Id, $deletable, true));
        });
    }

    /**
     * One node after a write, so the panel can patch itself instead of
     * refetching everything.
     *
     * @return array<string,mixed>
     */
    private function singleNode(StorageLocation $location, Registry $registry, User $user): array
    {
        $canManage = $this->canManage($user);
        $location->loadMissing(['type', 'purpose', 'status', 'parent']);

        return $this->node(
            $location,
            $registry,
            $this->nodeCounts($location, $user, $canManage),
            $canManage,
            $user->can('delete', $location)
        );
    }

    /**
     * The one definition of a tree node's shape.
     *
     * @param  array{directFiles:int,totalFiles:int,issues:int,overdue:int}  $counts
     * @return array<string,mixed>
     */
    private function node(
        StorageLocation $location,
        Registry $registry,
        array $counts,
        bool $canUpdate,
        bool $canDelete
    ): array {
        return [
            'id' => $location->Id,
            'parentId' => $location->ParentId,
            'code' => $location->Code,
            'label' => $location->Label ?: $location->Code,
            'path' => $location->Path,
            'depth' => $location->Depth,
            'type' => $location->type?->Description ?: 'Location',
            'typeValue' => $location->type?->Value,
            'purpose' => $location->purpose?->Description ?: '-',
            'status' => $location->status?->Description ?: '-',
            'statusValue' => $location->status?->Value,
            'parent' => $location->parent?->Label ?: $location->parent?->Code ?: $registry->Name,
            'capacity' => $location->Capacity,
            'barcode' => $location->Barcode,
            'sortOrder' => $location->SortOrder,
            'typeId' => $location->LocationTypeId,
            'purposeId' => $location->LocationPurposeId,
            'statusId' => $location->LocationStatusId,
            'canUpdate' => $canUpdate,
            'canDelete' => $canDelete,
            'available' => $location->Capacity === null
                ? null
                : max(0, $location->Capacity - $counts['totalFiles']),
            ...$counts,
        ];
    }

    /**
     * The same counts storageTree() derives in memory, for a single node.
     *
     * Paths cannot contain a LIKE metacharacter - StorageLocationService
     * restricts the code charset for exactly this reason - so the prefix match
     * is safe to build from one.
     *
     * @return array{directFiles:int,totalFiles:int,issues:int,overdue:int}
     */
    private function nodeCounts(StorageLocation $location, User $user, bool $canManage): array
    {
        $under = fn () => PhysicalFile::query()
            ->when(! $canManage, fn ($q) => $q->user($user))
            ->whereNull('DeletedOn')
            ->whereHas('currentLocation', fn ($q) => $q->where('Path', 'like', $location->Path . '%'));

        return [
            'directFiles' => PhysicalFile::query()
                ->when(! $canManage, fn ($q) => $q->user($user))
                ->whereNull('DeletedOn')
                ->where('CurrentLocationId', $location->Id)
                ->count(),
            'totalFiles' => $under()->count(),
            'issues' => $under()
                ->whereHas('status', fn ($q) => $q->whereIn('Value', ['missing', 'damaged']))
                ->count(),
            'overdue' => PhysicalFileCheckout::query()
                ->whereNull('ClosedOn')
                ->where('DueOn', '<', now())
                ->whereHas('physicalFile', fn ($q) => $q
                    ->when(! $canManage, fn ($files) => $files->user($user))
                    ->whereNull('DeletedOn')
                    ->whereHas('homeLocation', fn ($home) => $home
                        ->where('Path', 'like', $location->Path . '%')))
                ->count(),
        ];
    }

    /**
     * @return Collection<int,PhysicalFile>
     */
    private function registryFiles(Registry $registry, User $user, bool $canManage): Collection
    {
        return PhysicalFile::query()
            ->when(! $canManage, fn ($q) => $q->user($user))
            ->with(['currentLocation', 'homeLocation', 'status'])
            ->where('HomeRegistryId', $registry->Id)
            ->whereNull('DeletedOn')
            ->get();
    }

    /**
     * Everything this registry has lent out and not yet had back.
     *
     * The overdue list is a slice of the same thing, so both are drawn from
     * one query rather than asking the database the same question twice.
     *
     * @return Collection<int,PhysicalFileCheckout>
     */
    private function openCheckouts(Registry $registry, User $user, bool $canManage): Collection
    {
        return PhysicalFileCheckout::query()
            ->with(['physicalFile.homeLocation', 'borrower'])
            ->whereNull('ClosedOn')
            ->whereNull('DeletedOn')
            ->whereHas('physicalFile', fn ($q) => $q
                ->when(! $canManage, fn ($files) => $files->user($user))
                ->where('HomeRegistryId', $registry->Id)
                ->whereNull('DeletedOn'))
            ->orderBy('DueOn')
            ->get();
    }

    /**
     * @return Collection<int,PhysicalFileCheckout>
     */
    private function overdueCheckouts(Registry $registry, User $user, bool $canManage): Collection
    {
        return $this->openCheckouts($registry, $user, $canManage)
            ->filter(fn (PhysicalFileCheckout $checkout) => $checkout->DueOn->isPast());
    }

    /**
     * Custody picture for someone who works the registry.
     *
     * Only called for operators - a plain requester gets emptyDashboard() and
     * none of these queries run for them. The shelving tree is deliberately
     * absent: it arrives later over its own endpoint.
     *
     * @return array<string,mixed>
     */
    private function dashboard(Registry $registry, User $user, bool $canManage): array
    {
        $files = $this->registryFiles($registry, $user, $canManage);
        $onLoan = $this->openCheckouts($registry, $user, $canManage);
        $overdue = $onLoan->filter(fn (PhysicalFileCheckout $checkout) => $checkout->DueOn->isPast());

        $incoming = PhysicalFileMovement::query()
            ->with(['physicalFile', 'fromRegistry', 'fromLocation', 'fromHolder', 'toLocation'])
            ->where('ToRegistryId', $registry->Id)
            ->whereNull('ReceivedOn')
            ->whereHas('physicalFile', fn ($q) => $q
                ->when(! $canManage, fn ($files) => $files->user($user))
                ->whereNull('DeletedOn'))
            ->orderByDesc('DispatchedOn')
            ->get();

        $rejected = PhysicalFileRequest::query()
            ->whereHas('physicalFile', fn ($q) => $q
                ->where('HomeRegistryId', $registry->Id)
                ->whereNull('DeletedOn'))
            ->whereHas('status', fn ($q) => $q->where('Value', 'rejected'))
            ->whereNull('DeletedOn')
            ->count();

        $recent = PhysicalFileMovement::query()
            ->with([
                'physicalFile', 'movementType', 'fromRegistry', 'fromLocation', 'fromHolder',
                'toRegistry', 'toLocation', 'toHolder',
            ])
            ->whereHas('physicalFile', fn ($q) => $q
                ->when(! $canManage, fn ($files) => $files->user($user))
                ->where('HomeRegistryId', $registry->Id)
                ->whereNull('DeletedOn'))
            ->orderByDesc('DispatchedOn')
            ->limit(5)
            ->get();

        $issues = $files->filter(
            fn (PhysicalFile $file) =>
            in_array($file->status?->Value, ['missing', 'damaged'], true)
        );

        return [
            'incomingMovements' => $incoming,
            // Everything still out, so the return counter has one list to work
            // from; the overdue rows are the same loans, flagged.
            'onLoanCheckouts' => $onLoan,
            'overdueCheckouts' => $overdue->take(10),
            'filesWithIssues' => $issues->take(10),
            'recentMovements' => $recent,
            'incomingCounts' => [
                'files' => $incoming->count(),
                'filing' => $files->filter(
                    fn (PhysicalFile $file) =>
                    $file->status?->Value === 'in_tray'
                )->count(),
                'rejected' => $rejected,
                'overdue' => $overdue->count(),
                'issues' => $issues->count(),
            ],
        ];
    }

    /**
     * Resolves the registry in play, defaulting to the login branch's own.
     *
     * An explicit registry is accepted only if the branch rule allows it, so an
     * out-of-branch id is refused rather than quietly falling back.
     *
     * @throws ErroredException
     */
    private function resolveRegistry(Request $request): ?Registry
    {
        $user = $request->user();

        if (! $request->filled('registry')) {
            return $this->access->defaultRegistry($user);
        }

        $registry = Registry::query()->whereNull('DeletedOn')->find($request->integer('registry'));

        if (! $registry) {
            throw new ErroredException('That registry no longer exists.');
        }

        $this->access->assertAccessible($user, $registry);

        return $registry;
    }

    /**
     * The registry a location sits in, checked against the login branch.
     *
     * @throws ErroredException
     */
    private function registryFor(StorageLocation $location, Request $request): Registry
    {
        $registry = Registry::query()->findOrFail($location->RegistryId);

        $this->access->assertAccessible($request->user(), $registry);

        return $registry;
    }

    /**
     * @throws ErroredException
     */
    private function resolveParent(?int $parentId, Registry $registry): ?StorageLocation
    {
        if (! $parentId) {
            return null;
        }

        $parent = StorageLocation::query()->whereNull('DeletedOn')->find($parentId);

        if (! $parent || (int) $parent->RegistryId !== (int) $registry->Id) {
            throw new ErroredException('The parent location does not belong to this registry.');
        }

        return $parent;
    }

    /**
     * @return \Illuminate\Support\Collection<int,CodeDetail>
     */
    private function codes(string $bucket)
    {
        return CodeDetail::query()
            ->where('CodeID', $bucket)
            ->where('IsActive', true)
            ->whereNull('DeletedOn')
            ->orderBy('DisplayOrder')
            ->get(['ID', 'Value', 'Description']);
    }
}
