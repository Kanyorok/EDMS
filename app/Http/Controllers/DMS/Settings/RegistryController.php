<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\RegistryRequest;
use App\Models\Core\Branch;
use App\Models\DMS\Registry;
use App\Services\DMS\RegistryAccessService;
use App\Services\DMS\RegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

/**
 * Registry administration, restricted to head office.
 *
 * A registry decides which branch custodies a body of records, so creating one
 * is not something a branch may do for itself - it would amount to awarding
 * itself custody. Every action therefore passes two gates: the policy
 * (dmsRegistry-manage) and RegistryAccessService::assertHeadOffice().
 *
 * Managing the shelving *inside* a registry is a branch job and lives in
 * StorageLocationController, which uses the branch check instead.
 */
class RegistryController extends Controller
{
    public function __construct(
        private readonly RegistryService $registries,
        private readonly RegistryAccessService $access
    ) {
    }

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        try {
            $this->access->assertHeadOffice($request->user());
        } catch (ErroredException $e) {
            return $request->ajax()
                ? $this->errored($e->getMessage(), status: 403)
                : abort(403, $e->getMessage());
        }

        if ($request->ajax()) {
            try {
                return $this->table();
            } catch (Throwable $e) {
                Log::error('fetching (DMS) registries failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.settings.registries.index', [
            'branches' => Branch::query()->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Name', 'BranchID']),
            'parents' => Registry::query()->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Code', 'Name']),
        ]);
    }

    public function store(RegistryRequest $request): JsonResponse
    {
        $this->authorize('create', Registry::class);

        try {
            $this->access->assertHeadOffice($request->user());

            $registry = $this->registries->create($request->payload(), $request->user());

            activity()->causedBy($request->user())->performedOn($registry)
                ->event('create')->log('created registry ' . $registry->Code);

            return $this->succeeded("registry {$registry->Code} created successfully");
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('creating (DMS) registry failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(RegistryRequest $request, Registry $registry): JsonResponse
    {
        $this->authorize('update', $registry);

        try {
            $this->access->assertHeadOffice($request->user());

            $this->registries->update($registry, $request->payload(), $request->user());

            activity()->causedBy($request->user())->performedOn($registry)
                ->event('update')->log('updated registry ' . $registry->Code);

            return $this->succeeded("registry {$registry->Code} updated successfully");
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('updating (DMS) registry failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, Registry $registry): JsonResponse
    {
        $this->authorize('delete', $registry);

        try {
            $this->access->assertHeadOffice($request->user());

            $code = $registry->Code;
            $this->registries->delete($registry, $request->user());

            activity()->causedBy($request->user())->performedOn($registry)
                ->event('delete')->log('deleted registry ' . $code);

            return $this->succeeded("registry {$code} removed successfully");
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage());
        } catch (Throwable $e) {
            Log::error('deleting (DMS) registry failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    private function table(): JsonResponse
    {
        $query = Registry::query()
            ->lock('WITH(NOLOCK)')
            ->with(['status', 'hod', 'parent'])
            ->withCount(['locations' => fn ($q) => $q->whereNull('DeletedOn')])
            ->whereNull('DeletedOn');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('Branch', function (Registry $r) {
                $branch = $r->BranchId
                    ? Branch::query()->whereKey($r->BranchId)->value('Name')
                    : null;

                return e($branch ?: '-');
            })
            ->addColumn('Parent', fn (Registry $r) => e($r->parent ? $r->parent->Code : '-'))
            ->addColumn('Hod', fn (Registry $r) => e($r->hod ? $r->hod->Name : '-'))
            ->addColumn('Status', fn (Registry $r) => e($r->status ? $r->status->Description : '-'))
            ->addColumn('Archive', fn (Registry $r) => $r->IsArchive ? 'Yes' : 'No')
            ->editColumn('Description', fn (Registry $r) => e($r->Description ?: '-'))
            ->addColumn('action', function (Registry $r) {
                $payload = e(json_encode([
                    'Id' => $r->Id,
                    'Code' => $r->Code,
                    'Name' => $r->Name,
                    'Description' => $r->Description,
                    'ParentRegistryId' => $r->ParentRegistryId,
                    'BranchId' => $r->BranchId,
                    'HodUserID' => $r->hod ? $r->hod->UserID : null,
                    'HodName' => $r->hod ? $r->hod->Name : null,
                    'RegistryStatusId' => $r->RegistryStatusId,
                    'IsArchive' => (bool) $r->IsArchive,
                    'Visibility' => $r->Visibility->value,
                ], JSON_THROW_ON_ERROR));

                $edit = '<button type="button" class="btn btn-sm btn-outline-primary edit-registry" '
                    . 'data-registry="' . $payload . '"><i class="fas fa-edit"></i></button>';
                $delete = '<button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-registry" '
                    . 'data-id="' . $r->Id . '" data-code="' . e($r->Code) . '"><i class="fas fa-trash"></i></button>';

                return '<div class="text-nowrap">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make();
    }
}
