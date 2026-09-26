<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\SourceSystemRequest;
use App\Models\Auth\User;
use App\Models\DMS\SourceSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class SourceSystemController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', SourceSystem::class);

        if ($request->ajax()) {
            try {
                return $this->table();
            } catch (Throwable $e) {
                Log::error('fetching (DMS) source systems failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.settings.source-systems.index', [
            'users' => User::query()->orderBy('Name')->get(['Id', 'Name']),
        ]);
    }

    public function store(SourceSystemRequest $request): JsonResponse
    {
        $this->authorize('create', SourceSystem::class);

        try {
            $system = SourceSystem::create($request->validated() + [
                'CreatedBy' => $request->user()->Id,
                'ModifiedBy' => $request->user()->Id,
            ]);

            activity()->causedBy($request->user())->performedOn($system)
                ->event('create')->log('created source system ' . $system->Code);

            return $this->succeeded("source system {$system->Code} created successfully");
        } catch (Throwable $e) {
            Log::error('creating (DMS) source system failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(SourceSystemRequest $request, SourceSystem $sourceSystem): JsonResponse
    {
        $this->authorize('update', $sourceSystem);

        try {
            $sourceSystem->update($request->validated() + ['ModifiedBy' => $request->user()->Id]);

            activity()->causedBy($request->user())->performedOn($sourceSystem)
                ->event('update')->log('updated source system ' . $sourceSystem->Code);

            return $this->succeeded("source system {$sourceSystem->Code} updated successfully");
        } catch (Throwable $e) {
            Log::error('updating (DMS) source system failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, SourceSystem $sourceSystem): JsonResponse
    {
        $this->authorize('delete', $sourceSystem);

        // Its events (and the documents filed under them) would be left pointing at nothing.
        if ($sourceSystem->eventTypes()->exists()) {
            return $this->errored("source system {$sourceSystem->Code} still has event types; remove or move them first");
        }

        try {
            $code = $sourceSystem->Code;
            $sourceSystem->DeletedBy = $request->user()->Id;
            $sourceSystem->save();
            $sourceSystem->delete();

            activity()->causedBy($request->user())->performedOn($sourceSystem)
                ->event('delete')->log('deleted source system ' . $code);

            return $this->succeeded("source system {$code} removed successfully");
        } catch (Throwable $e) {
            Log::error('deleting (DMS) source system failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    private function table(): JsonResponse
    {
        $query = SourceSystem::query()->lock('WITH(NOLOCK)')->with('serviceUser:Id,Name')->withCount('eventTypes')->whereNull('DeletedOn');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('Description', fn (SourceSystem $s) => e($s->Description ?: '-'))
            ->addColumn('ServiceUser', fn (SourceSystem $s) => e($s->serviceUser?->Name ?? '-'))
            ->addColumn('Events', fn (SourceSystem $s) => $s->event_types_count)
            ->addColumn('Active', fn (SourceSystem $s) => $s->IsActive ? 'Active' : 'Inactive')
            ->addColumn('action', function (SourceSystem $s) {
                $payload = e(json_encode([
                    'Id' => $s->Id,
                    'Code' => $s->Code,
                    'Name' => $s->Name,
                    'Description' => $s->Description,
                    'ServiceUserId' => $s->ServiceUserId,
                    'IsActive' => (bool) $s->IsActive,
                ], JSON_THROW_ON_ERROR));

                $edit = '<button type="button" class="btn btn-sm btn-outline-primary edit-source-system" '
                    . 'data-system="' . $payload . '"><i class="fas fa-edit"></i></button>';
                $delete = '<button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-source-system" '
                    . 'data-id="' . $s->Id . '" data-code="' . e($s->Code) . '"><i class="fas fa-trash"></i></button>';

                return '<div class="text-nowrap">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make();
    }
}
