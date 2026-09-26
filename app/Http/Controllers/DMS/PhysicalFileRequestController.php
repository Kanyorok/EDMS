<?php

namespace App\Http\Controllers\DMS;

use App\Enums\Core\PermissionEnum;
use App\Exceptions\ErroredException;
use App\Http\Controllers\Controller;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\PhysicalFileRequest;
use App\Models\DMS\Registry;
use App\Services\DMS\PhysicalFileService;
use App\Services\DMS\RegistryAccessService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class PhysicalFileRequestController extends Controller
{
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
                return DataTables::of(PhysicalFileRequest::query()->whereRaw('1 = 0'))
                    ->addIndexColumn()->make();
            }

            $user = $request->user();
            $canDecide = $this->physicalFiles->canApprove($registry, $user);
            $canOperate = $canDecide || $user->can(PermissionEnum::DMSRegistryManage->value);

            $query = PhysicalFileRequest::query()
                ->lock('WITH(NOLOCK)')
                ->with(['physicalFile', 'requester', 'status'])
                ->whereHas('physicalFile', fn ($q) => $q->where('HomeRegistryId', $registry->Id))
                ->when(! $canOperate, fn ($q) => $q->where('RequestedByUserId', $user->Id));

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('File', fn (PhysicalFileRequest $item) => e(
                    $item->physicalFile
                        ? $item->physicalFile->FileNumber . ' - ' . $item->physicalFile->Title
                        : '-'
                ))
                ->addColumn('Requester', fn (PhysicalFileRequest $item) => e(
                    $item->requester?->Name ?: '-'
                ))
                // Both dates are NOT NULL on the table, so no nullsafe needed.
                ->addColumn('Period', fn (PhysicalFileRequest $item) => e(
                    $item->NeededFrom->format('d M Y') . ' – ' . $item->RequestedUntil->format('d M Y')
                ))
                ->addColumn('Status', fn (PhysicalFileRequest $item) => e(
                    $item->status?->Description ?: '-'
                ))
                ->editColumn('Purpose', fn (PhysicalFileRequest $item) => e($item->Purpose))
                ->addColumn('action', function (PhysicalFileRequest $item) use ($canDecide, $user) {
                    // The history reads the same for everyone; only a pending
                    // request can still be answered.
                    $status = $item->status?->Value;
                    $fileNumber = e($item->physicalFile?->FileNumber ?: '');
                    $ownRequest = (int) $item->RequestedByUserId === (int) $user->Id;

                    $buttons = '<button type="button" class="btn btn-sm btn-outline-secondary request-history" '
                        . 'data-id="' . $item->Id . '" '
                        . 'data-file="' . e($item->physicalFile?->FileNumber ?: 'Request') . '" '
                        . 'title="Request history"><i class="fas fa-clock-rotate-left"></i></button>';

                    // Nobody answers their own ask, so those rows offer no
                    // decision even to an approver.
                    if ($canDecide && $status === 'pending' && ! $ownRequest) {
                        $buttons .= ' <button type="button" class="btn btn-sm btn-success decide-request" '
                            . 'data-id="' . $item->Id . '" data-decision="approve" '
                            . 'data-file="' . $fileNumber . '">Approve</button>'
                            . ' <button type="button" class="btn btn-sm btn-outline-danger decide-request" '
                            . 'data-id="' . $item->Id . '" data-decision="reject" '
                            . 'data-file="' . $fileNumber . '">Reject</button>';
                    }

                    // An approval is a promise until the file actually changes
                    // hands; until then it can be honoured or taken back.
                    if ($status === 'approved') {
                        if ($canDecide) {
                            $buttons .= ' <button type="button" class="btn btn-sm btn-primary handover-request" '
                                . 'data-id="' . $item->Id . '" data-file="' . $fileNumber . '" '
                                . 'data-requester="' . e($item->requester?->Name ?: 'the requester') . '">'
                                . '<i class="fas fa-hand-holding"></i> Hand over</button>';
                        }

                        if ($canDecide || (int) $item->DecidedByUserId === (int) $user->Id) {
                            $buttons .= ' <button type="button" class="btn btn-sm btn-outline-danger cancel-request" '
                                . 'data-id="' . $item->Id . '" data-file="' . $fileNumber . '">Cancel</button>';
                        }
                    }

                    return '<div class="text-nowrap">' . $buttons . '</div>';
                })
                ->rawColumns(['action'])
                ->make();
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('fetching (DMS) physical file requests failed : ' . $e);

            return $this->errored('fetching requests failed, try again later');
        }
    }

    /**
     * The story behind one request, for whoever has to decide it.
     *
     * A decision is rarely about the request alone: it is about who has had
     * this file before, whether they brought it back, and where it is now. All
     * of that is gathered here so the panel can be read before answering.
     */
    public function history(Request $request, PhysicalFileRequest $physicalFileRequest): View
    {
        $this->authorize('viewAny', Registry::class);

        $user = $request->user();
        $file = $physicalFileRequest->physicalFile()->firstOrFail();

        try {
            $registry = $file->homeRegistry()->firstOrFail();
            $this->access->assertAccessible($user, $registry);
        } catch (ErroredException $e) {
            abort(403, $e->getMessage());
        }

        $canDecide = $this->physicalFiles->canApprove($registry, $user);
        $canOperate = $canDecide || $user->can(PermissionEnum::DMSRegistryManage->value)
            || $user->can(PermissionEnum::Admin->value);

        // Your own request, or one you are trusted with.
        abort_unless(
            $canOperate || (int) $physicalFileRequest->RequestedByUserId === (int) $user->Id,
            403
        );

        $physicalFileRequest->loadMissing(['requester', 'status', 'decidedBy', 'cancelledBy']);
        $file->loadMissing(['status', 'currentHolder', 'currentLocation', 'homeLocation']);

        $ownRequest = (int) $physicalFileRequest->RequestedByUserId === (int) $user->Id;

        return view('dms.registry.requests._history', [
            'request' => $physicalFileRequest,
            'file' => $file,
            'registry' => $registry,
            // An approver still may not answer their own ask.
            'canDecide' => $canDecide && ! $ownRequest,
            'canHandOver' => $canDecide,
            'canCancel' => $this->physicalFiles->canCancel($physicalFileRequest, $registry, $user),
            // Everything asked of this file before, most recent first.
            'earlier' => $file->requests()
                ->with(['requester', 'status', 'decidedBy'])
                ->whereKeyNot($physicalFileRequest->Id)
                ->whereNull('DeletedOn')
                ->orderByDesc('RequestedOn')
                ->limit(10)
                ->get(),
            // And whether it came back when it was lent out.
            'loans' => $file->checkouts()
                ->with(['borrower', 'issuedBy', 'status'])
                ->whereNull('DeletedOn')
                ->orderByDesc('CheckedOutOn')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        $validated = $request->validate([
            'PhysicalFileId' => ['required', 'integer', 'exists:t_DMSPhysicalFiles,Id'],
            'NeededFrom' => ['required', 'date', 'after_or_equal:today'],
            'RequestedUntil' => ['required', 'date', 'after_or_equal:NeededFrom'],
            'Purpose' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $file = PhysicalFile::query()->findOrFail($validated['PhysicalFileId']);
            $registry = $file->homeRegistry()->firstOrFail();
            $this->access->assertAccessible($request->user(), $registry);

            $physicalRequest = $this->physicalFiles->request(
                $file,
                $request->user(),
                CarbonImmutable::parse($validated['NeededFrom'])->startOfDay(),
                CarbonImmutable::parse($validated['RequestedUntil'])->endOfDay(),
                $validated['Purpose']
            );

            activity()->causedBy($request->user())->performedOn($physicalRequest)
                ->event('create')->log('requested physical file ' . $file->FileNumber);

            return $this->succeeded('physical file requested successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('creating (DMS) physical file request failed : ' . $e);

            return $this->errored('creating the request failed, try again later');
        }
    }

    public function update(Request $request, PhysicalFileRequest $physicalFileRequest): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $file = $physicalFileRequest->physicalFile()->with('homeRegistry')->firstOrFail();
            $this->access->assertAccessible($request->user(), $file->homeRegistry);

            $this->physicalFiles->decide(
                $physicalFileRequest,
                $request->user(),
                $validated['decision'] === 'approve',
                $validated['remark'] ?? null
            );

            $pastTense = $validated['decision'] === 'approve' ? 'approved' : 'rejected';

            activity()->causedBy($request->user())->performedOn($physicalFileRequest)
                ->event($validated['decision'])
                ->log($pastTense . ' the request for physical file ' . $file->FileNumber);

            return $this->succeeded('physical file request ' . $pastTense . ' successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('deciding (DMS) physical file request failed : ' . $e);

            return $this->errored('updating the request failed, try again later');
        }
    }

    /**
     * Gives the file to whoever asked for it.
     *
     * The approval only said yes; this is the counter moment when the paper
     * leaves the shelf, so custody passes to the requester and the loan that
     * has to be closed by a return starts here.
     */
    public function handover(Request $request, PhysicalFileRequest $physicalFileRequest): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        $validated = $request->validate([
            'HandoverRemark' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $file = $physicalFileRequest->physicalFile()->with('homeRegistry')->firstOrFail();
            $this->access->assertAccessible($request->user(), $file->homeRegistry);

            $checkout = $this->physicalFiles->checkout(
                $physicalFileRequest,
                $request->user(),
                $validated['HandoverRemark'] ?? null
            );

            activity()->causedBy($request->user())->performedOn($checkout)
                ->event('handover')
                ->log('handed physical file ' . $file->FileNumber . ' to '
                    . ($checkout->borrower?->Name ?: 'the requester'));

            return $this->succeeded('physical file handed over successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('handing over (DMS) physical file failed : ' . $e);

            return $this->errored('handing over the physical file failed, try again later');
        }
    }

    /**
     * Withdraws an approval the registry no longer stands behind.
     *
     * A reason is required: the requester was told the file was theirs to
     * collect, so they are owed an account of why it no longer is.
     */
    public function cancel(Request $request, PhysicalFileRequest $physicalFileRequest): JsonResponse
    {
        $this->authorize('viewAny', Registry::class);

        $validated = $request->validate([
            'CancellationReason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $file = $physicalFileRequest->physicalFile()->with('homeRegistry')->firstOrFail();
            $this->access->assertAccessible($request->user(), $file->homeRegistry);

            $this->physicalFiles->cancel(
                $physicalFileRequest,
                $request->user(),
                $validated['CancellationReason']
            );

            activity()->causedBy($request->user())->performedOn($physicalFileRequest)
                ->event('cancel')
                ->log('cancelled the approved request for physical file ' . $file->FileNumber);

            return $this->succeeded('approved request cancelled successfully');
        } catch (ErroredException $e) {
            return $this->errored($e->getMessage(), status: 403);
        } catch (Throwable $e) {
            Log::error('cancelling (DMS) physical file request failed : ' . $e);

            return $this->errored('cancelling the request failed, try again later');
        }
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
