<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Core\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\BranchRequest;
use App\Jobs\Core\SyncNmbBranchesJob;
use App\Models\Core\Branch;
use App\Models\Core\BranchSyncRun;
use App\Models\Core\SyncedBranch;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class CrmBranchController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax')->except([
            'index',
            'sync',
            'startSync',
            'getSyncProgress',
            'cancelSync',
        ]);
    }

    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function index(Request $request): JsonResponse|View
    {
        $this->authorize('viewAny', Branch::class);
        if ($request->ajax()) {
            return Datatables::of(Branch::query()->select('*'))->addIndexColumn()
                ->addColumn('action', function (Branch $branch) {
                    $actions = '';
                    if (auth()->user()->can(PermissionEnum::BranchView->value)) {
                        $actions .= '<button type="button" class="btn btn-info btn-sm me-1 branch-action-view" data-info="' . $branch->BranchID . '~' . $branch->Name . '~' . $branch->Address . '~' . $branch->Address2 . '~' . $branch->Phone . '~' . $branch->Email . '"
                       data-manager="' . $branch->manager?->UserID . '~' . $branch->manager?->Name . '" data-operation="' . $branch->operation?->UserID . '~' . $branch->operation?->Name . '"><i class="fas fa-eye"></i> View</button>';
                    }
                    if (auth()->user()->can(PermissionEnum::BranchUpdate->value)) {
                        $actions .= '<button type="button" class="btn btn-primary btn-sm branch-action-update me-1" data-info="' . $branch->BranchID . '~' . $branch->Name . '~' . $branch->Address . '~' . $branch->Address2 . '~' . $branch->Phone . '~' . $branch->Email . '"
                       data-manager="' . $branch->manager?->UserID . '~' . $branch->manager?->Name . '" data-operation="' . $branch->operation?->UserID . '~' . $branch->operation?->Name . '" data-route="' . route('branches.update', [$branch->Id]) . '" ><i class="fas fa-edit"></i> Edit</button>';
                    }
                    if (auth()->user()->can(PermissionEnum::BranchDelete->value)) {
                        $actions .= '<button type="button" class="btn btn-danger btn-sm branch-action-trash" data-info="' . $branch->BranchID . '~' . $branch->Name . '"  data-route="' . route('branches.destroy', [$branch->Id]) . '"><i class="fas fa-trash"></i> Trash</button>';
                    }

                    return $actions;
                })->addColumn('Manager', function (Branch $branch) {
                    return $branch->manager?->Name;
                })->editColumn('Address', function (Branch $branch) {
                    return $branch->Address ?? '';
                })->editColumn('Address2', function (Branch $branch) {
                    return $branch->Address2 ?? '';
                })->addColumn('Operation', function (Branch $branch) {
                    return $branch->operation?->Name;
                })->rawColumns(['action', 'Manager', 'Operation'])->make();
        }

        return view('settings.branches.index');
    }

    /**
     * Store a newly created resource in storage.
     * @throws ValidationException
     */
    public function store(BranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);
        $userID = $request->getManager()?->Id ?? null;
        $Operation = $request->getOperation()?->Id ?? null;
        $branchID = $request->getBranchID();
        $actor = $request->user();

        try {
            DB::transaction(static function () use ($branchID, $Operation, $actor, $request, $userID) {
                $crmBranch = Branch::create([
                    'Name' => $request->string('Name'),
                    'Address' => $request->string('Address', ''),
                    'Address2' => $request->string('Address2', ''),
                    'Phone' => $request->string('Phone', ''),
                    'Email' => $request->string('Email', ''),
                    'BranchID' => $branchID,
                    'UserId' => $userID,
                    'OperationId' => $Operation,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                    'CreatedOn' => now(),
                    'UpdatedOn' => now(),
                ]);

                activity()->causedBy($actor)->performedOn($crmBranch->refresh())->event('create')->log('created a branch ' . $crmBranch->BranchID);
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error creating branch failed: ' . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('branch added successfully');
    }

    public function update(BranchRequest $request, Branch $crmBranch): JsonResponse
    {
        $this->authorize('update', $crmBranch);
        $userID = $request->getManager()?->Id ?? null;
        $Operation = $request->getOperation()?->Id ?? null;
        $actor = $request->user();

        try {
            DB::transaction(static function () use ($crmBranch, $Operation, $actor, $request, $userID) {

                $crmBranch->update([
                    'Name' => $request->string('Name'),
                    'Address' => $request->string('Address', ''),
                    'Address2' => $request->string('Address2', ''),
                    'Phone' => $request->string('Phone', ''),
                    'Email' => $request->string('Email', ''),
                    'UserId' => $userID,
                    'OperationId' => $Operation,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($crmBranch)->event('update')->log('updated branch ' . $crmBranch->BranchID);
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error updating branch failed: ' . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('branch updated successfully');
    }

    public function destroy(Request $request, Branch $crmBranch): JsonResponse
    {
        $this->authorize('delete', $crmBranch);

        try {
            DB::transaction(static function () use ($request, $crmBranch) {
                $crmBranch->forceFill([
                    'DeletedOn' => now(),
                    'DeletedBy' => $request->user()->Id,
                ])->save();
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error trashing branch failed: ' . $e->getMessage());

            return $this->errored('unexpected error, try again later');
        }

        return $this->succeeded('branch trashed successfully');
    }

    public function sync()
    {
        $this->authorize(PermissionEnum::BranchSyncAct, Branch::class);

        $searchBranch = trim((string) request('branch_account', ''));

        $syncCount = SyncedBranch::count();
        $lastSyncedAt = SyncedBranch::max('ModifiedOn');
        $syncedBranchesQuery = SyncedBranch::query()->select([
            'Id',
            'BranchID',
            'Name',
            'Address',
            'Address2',
            'City',
            'State',
            'Zip',
            'Country',
            'Phone',
            'Fax',
            'Email',
            'IsHQ',
            'IsActive',
            'Source',
            'SourceTable',
            'CreatedOn',
            'ModifiedOn',
        ]);

        if ($searchBranch !== '') {
            $syncedBranchesQuery->where(function ($query) use ($searchBranch) {
                $query->where('BranchID', $searchBranch)
                    ->orWhere('Name', 'like', '%' . $searchBranch . '%');
            });
        }

        $syncedBranches = $syncedBranchesQuery
            ->orderBy('BranchID')
            ->paginate(40)
            ->withQueryString();

        $selectedBranchOption = null;
        if ($searchBranch !== '') {
            $selectedBranch = SyncedBranch::query()
                ->where('BranchID', $searchBranch)
                ->orWhere('Name', 'like', '%' . $searchBranch . '%')
                ->select('BranchID', 'Name')
                ->orderBy('BranchID')
                ->first();

            if ($selectedBranch) {
                $selectedBranchOption = [
                    'id' => $selectedBranch->BranchID,
                    'text' => trim($selectedBranch->BranchID . ' (' . ($selectedBranch->Name ?? '-') . ')'),
                ];
            }
        }

        $activeSync = BranchSyncRun::whereIn('Status', ['pending', 'running'])
            ->orderBy('Id', 'desc')
            ->first();
        $latestSync = BranchSyncRun::orderBy('Id', 'desc')->first();

        return view('settings.branches.sync', compact(
            'syncCount',
            'lastSyncedAt',
            'syncedBranches',
            'selectedBranchOption',
            'activeSync',
            'latestSync'
        ));
    }

    public function searchSyncedBranches(Request $request)
    {
        abort_unless(
            auth()->user()->can(PermissionEnum::BranchSyncAct->value)
                || auth()->user()->can(PermissionEnum::BranchUpdate->value)
                || auth()->user()->can(PermissionEnum::BranchCreate->value),
            403
        );

        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $query = SyncedBranch::query()
            ->where(function ($query) use ($q) {
                $query->where('BranchID', 'like', '%' . $q . '%')
                    ->orWhere('Name', 'like', '%' . $q . '%');
            });

        if ($request->boolean('unmapped_only')) {
            // Include soft-deleted branches too: their BranchID still occupies the
            // unique index in t_Branches, so it can't be reused even though the
            // row is trashed.
            $query->whereNotIn('BranchID', Branch::withTrashed()->pluck('BranchID'));
        }

        $results = $query
            ->select('BranchID', 'Name', 'Address', 'Address2', 'City', 'State', 'Zip', 'Country', 'Phone', 'Email')
            ->orderBy('BranchID')
            ->limit(25)
            ->get()
            ->map(function ($branch) {
                return [
                    'id' => $branch->BranchID,
                    'text' => trim($branch->BranchID . ' (' . ($branch->Name ?? '-') . ')'),
                    'code' => $branch->BranchID,
                    'name' => $branch->Name,
                    'address' => $branch->Address,
                    'address2' => $branch->Address2,
                    'city' => $branch->City,
                    'state' => $branch->State,
                    'zip' => $branch->Zip,
                    'country' => $branch->Country,
                    'phone' => $branch->Phone,
                    'email' => $branch->Email,
                ];
            })
            ->values();

        return response()->json(['results' => $results]);
    }

    public function startSync(Request $request)
    {
        $this->authorize(PermissionEnum::BranchSyncAct, Branch::class);
        $request->validate([
            'page_size' => 'nullable|integer|min:1|max:5000',
        ]);

        $userId = Auth::id() ?? 1;
        $pageSize = max(1, min(5000, (int) $request->input('page_size', 1000)));

        $activeSync = BranchSyncRun::whereIn('Status', ['pending', 'running'])
            ->orderBy('Id', 'desc')
            ->first();

        if ($activeSync) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'A branch sync is already running. Please wait for it to complete.',
                    'syncRunId' => $activeSync->Id,
                ], 409);
            }

            return back()->with('error', 'A branch sync is already running. Please wait for it to complete.');
        }

        $now = now();
        $syncRun = BranchSyncRun::create([
            'Source' => 'NIMBLE',
            'Status' => 'pending',
            'Message' => 'Sync queued.',
            'RecordsSynced' => 0,
            'RecordsFailed' => 0,
            'TotalRecords' => null,
            'CurrentPage' => 0,
            'PageSize' => $pageSize,
            'LastCursor' => null,
            'StartedAt' => $now,
            'CreatedBy' => $userId,
            'CreatedOn' => $now,
            'ModifiedBy' => $userId,
            'ModifiedOn' => $now,
        ]);

        $queueConnection = trim((string) config('services.branch_sync.queue_connection', config('queue.default', 'database')));
        if ($queueConnection === '' || ! config()->has('queue.connections.' . $queueConnection)) {
            $queueConnection = (string) config('queue.default', 'database');
        }

        // Force async dispatch for this heavy sync job.
        if ($queueConnection === 'sync' && config()->has('queue.connections.database')) {
            $queueConnection = 'database';
        }

        try {
            SyncNmbBranchesJob::dispatch($syncRun->Id)->onConnection($queueConnection);
        } catch (\Throwable $e) {
            Log::error('Failed to queue branch sync job.', [
                'syncRunId' => $syncRun->Id,
                'queueConnection' => $queueConnection,
                'message' => $e->getMessage(),
            ]);

            $syncRun->update([
                'Status' => 'failed',
                'Message' => 'Failed to queue branch sync job.',
                'SyncError' => $e->getMessage(),
                'CompletedAt' => now(),
                'ModifiedBy' => $userId,
                'ModifiedOn' => now(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to queue branch sync job. Check queue configuration and worker status.',
                ], 500);
            }

            return back()->with('error', 'Failed to queue branch sync job. Check queue configuration and worker status.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Branch sync started in background.',
                'syncRunId' => $syncRun->Id,
                'dispatchMode' => 'queue_worker',
                'queueConnection' => $queueConnection,
            ]);
        }

        return back()
            ->with('success', 'Branch sync started in background.')
            ->with('active_sync_id', $syncRun->Id);
    }

    public function getSyncProgress(int $syncRunId)
    {
        $this->authorize(PermissionEnum::BranchSyncAct, Branch::class);

        $syncRun = BranchSyncRun::find($syncRunId);
        if (! $syncRun) {
            return response()->json([
                'success' => false,
                'message' => 'Sync run not found.',
            ], 404);
        }

        $totalRecords = (int) ($syncRun->TotalRecords ?? 0);
        $syncedRecords = (int) ($syncRun->RecordsSynced ?? 0);
        $percentage = $totalRecords > 0
            ? min(100, round(($syncedRecords / $totalRecords) * 100, 1))
            : null;

        return response()->json([
            'success' => true,
            'id' => $syncRun->Id,
            'status' => $syncRun->Status,
            'message' => $syncRun->Message,
            'recordsSynced' => $syncedRecords,
            'recordsFailed' => (int) ($syncRun->RecordsFailed ?? 0),
            'totalRecords' => $totalRecords,
            'currentPage' => (int) ($syncRun->CurrentPage ?? 0),
            'pageSize' => (int) ($syncRun->PageSize ?? 1000),
            'lastCursor' => $syncRun->LastCursor,
            'percentage' => $percentage,
            'isComplete' => $syncRun->Status === 'completed',
            'isFailed' => $syncRun->Status === 'failed',
            'isCancelled' => $syncRun->Status === 'cancelled',
            'error' => $syncRun->SyncError,
            'completedAt' => optional($syncRun->CompletedAt)->toDateTimeString(),
            'startedAt' => optional($syncRun->StartedAt)->toDateTimeString(),
        ]);
    }

    public function cancelSync(Request $request, int $syncRunId)
    {
        $this->authorize(PermissionEnum::BranchSyncAct, Branch::class);

        $syncRun = BranchSyncRun::find($syncRunId);
        if (! $syncRun) {
            return response()->json([
                'success' => false,
                'message' => 'Sync run not found.',
            ], 404);
        }

        if (! in_array($syncRun->Status, ['pending', 'running'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or running syncs can be cancelled.',
                'syncRunId' => $syncRun->Id,
                'status' => $syncRun->Status,
            ], 409);
        }

        $wasPending = $syncRun->Status === 'pending';
        $syncRun->update([
            'Status' => 'cancelled',
            'Message' => $wasPending
                ? 'Branch sync cancelled before processing started.'
                : 'Branch sync cancellation requested. Waiting for worker to stop.',
            'SyncError' => null,
            'CompletedAt' => now(),
            'ModifiedBy' => Auth::id() ?? $syncRun->ModifiedBy ?? $syncRun->CreatedBy ?? 1,
            'ModifiedOn' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $wasPending
                ? 'Queued branch sync cancelled.'
                : 'Running branch sync cancellation requested.',
            'syncRunId' => $syncRun->Id,
            'status' => 'cancelled',
        ]);
    }
}
