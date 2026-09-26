<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\ValidationTypeRequest;
use App\Models\Core\Approval\Workflow;
use App\Models\DMS\DocumentValidationType;
use App\Services\DMS\Verification\ValidationTypeService;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class DocumentValidationTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->ajax()) {
            try {
                return Datatables::of(DocumentValidationType::query()->lock('WITH(NOLOCK)')->withCount('validations'))->addIndexColumn()
                    ->addColumn('action', function (DocumentValidationType $type) {
                        return '<a  href="' . route('document-validation-type.show', [$type->ValidationTypeId]) . '" class="btn btn-info btn-sm"><i class="fas fa-eye"></i> details</button>';
                    })->editColumn('validations_count', function (DocumentValidationType $type) {
                        return number_format($type->validations_count);
                    })->addColumn('Notes', function (DocumentValidationType $type) {
                        return Str::of($type->Notes)->limit(100);
                    })->rawColumns(['action'])->make();
            } catch (Exception $e) {
                Log::error('fetching (DMS) Document Validation Types failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.validation.types.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ValidationTypeRequest $request)
    {
        $approvers = $request->getApprovers();

        try {
            return DB::transaction(function () use ($request, $approvers) {
                $type = ValidationTypeService::create($request->string('Name')->trim()->toString(), $request->user(), $request->string('Notes', null)->trim()->toString(), $approvers)->type;

                return $this->succeeded("validation type {$type->ValidationTypeId} created successfully");
            });
        } catch (Throwable $e) {
            Log::error('creating (DMS) validation type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * Display the specified resource.
     */
    public function show(DocumentValidationType $documentValidationType)
    {
        $this->authorize('view', $documentValidationType);

        return view('dms.validation.types.show', [
            'type' => $documentValidationType->loadCount('validations')->load('workflow'),
            // Workflows an admin has built against this source in Settings -> Workflows. One is
            // bound per type; leaving it unset keeps the type on single-step approval.
            'workflows' => Workflow::query()->where('Source', 't_DocumentValidations')
                ->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Name']),
        ]);
    }

    /**
     * Bind (or clear) the approval workflow that governs how documents of this type are validated.
     */
    public function workflow(Request $request, DocumentValidationType $documentValidationType): JsonResponse
    {
        $this->authorize('update', $documentValidationType);

        $validated = $request->validate([
            'WorkFlowId' => [
                'nullable',
                Rule::exists('t_Workflows', 'Id')->where(
                    fn ($q) => $q->where('Source', 't_DocumentValidations')->whereNull('DeletedOn')
                ),
            ],
        ]);

        $workFlowId = $validated['WorkFlowId'] ?? null;

        try {
            return DB::transaction(function () use ($documentValidationType, $request, $workFlowId) {
                $documentValidationType->update([
                    'WorkFlowId' => $workFlowId,
                    'ModifiedBy' => $request->user()->Id,
                ]);

                activity()->causedBy($request->user())->performedOn($documentValidationType)->event('update')
                    ->log(($workFlowId ? 'bound approval workflow to' : 'cleared approval workflow from')
                        . ' validation type ' . $documentValidationType->ValidationTypeId);

                return $this->succeeded(
                    $workFlowId ? 'approval workflow updated successfully' : 'approval workflow cleared',
                    route: route('document-validation-type.show', [$documentValidationType->ValidationTypeId])
                );
            });
        } catch (Throwable $e) {
            Log::error('binding (DMS) validation type workflow failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DocumentValidationType $documentValidationType): JsonResponse
    {
        $this->authorize('update', $documentValidationType);

        $validated = $request->validate([
            "Name" => "required|string|max:255",
            "Notes" => "nullable|string|max:5000",
        ]);

        try {
            return DB::transaction(function () use ($request, $documentValidationType, $validated) {
                $documentValidationType->update([
                    "Name" => $validated['Name'],
                    "Notes" => $validated['Notes'],
                    "ModifiedBy" => $request->user()->Id,
                ]);
                activity()->causedBy($request->user())->performedOn($documentValidationType)->event('update')->log('updated validation type ' . $documentValidationType->ValidationTypeId);

                return $this->succeeded("validation type {$documentValidationType->ValidationTypeId} updated successfully", route: route('document-validation-type.show', [$documentValidationType->ValidationTypeId]));
            });
        } catch (Throwable $e) {
            Log::error('updating (DMS) validation type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, DocumentValidationType $documentValidationType): JsonResponse
    {
        $this->authorize('delete', $documentValidationType);

        try {
            return DB::transaction(function () use ($documentValidationType, $request) {
                $documentValidationType->forceFill([
                    'DeletedOn' => now(),
                    'DeletedBy' => $request->user()->Id,
                ])->save();
                activity()->causedBy($request->user())->performedOn($documentValidationType)->event('delete')->log('deleted validation type ' . $documentValidationType->ValidationTypeId);

                return $this->succeeded("validation type {$documentValidationType->ValidationTypeId} trashed successfully", route: route('document-validation-type.index'));
            });
        } catch (Throwable $e) {
            Log::error('deleting (DMS) validation type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }
}
