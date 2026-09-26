<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\DMS\Files\DocumentRelationOptionsController;
use App\Http\Requests\DMS\LinkedToTypeRequest;
use App\Models\DMS\LinkedToType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class LinkedToTypeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', LinkedToType::class);

        if ($request->ajax()) {
            try {
                return $this->table();
            } catch (Throwable $e) {
                Log::error('fetching (DMS) linked-to types failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.settings.linked-to-types.index', [
            'relationTypes' => DocumentRelationOptionsController::RELATION_TYPES,
        ]);
    }

    public function store(LinkedToTypeRequest $request): JsonResponse
    {
        $this->authorize('create', LinkedToType::class);

        try {
            $type = LinkedToType::create($request->validated() + [
                'CreatedBy' => $request->user()->Id,
                'ModifiedBy' => $request->user()->Id,
            ]);

            activity()->causedBy($request->user())->performedOn($type)
                ->event('create')->log('created linked-to type ' . $type->Code);

            return $this->succeeded("linked-to type {$type->Code} created successfully");
        } catch (Throwable $e) {
            Log::error('creating (DMS) linked-to type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(LinkedToTypeRequest $request, LinkedToType $linkedToType): JsonResponse
    {
        $this->authorize('update', $linkedToType);

        try {
            $linkedToType->update($request->validated() + ['ModifiedBy' => $request->user()->Id]);

            activity()->causedBy($request->user())->performedOn($linkedToType)
                ->event('update')->log('updated linked-to type ' . $linkedToType->Code);

            return $this->succeeded("linked-to type {$linkedToType->Code} updated successfully");
        } catch (Throwable $e) {
            Log::error('updating (DMS) linked-to type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, LinkedToType $linkedToType): JsonResponse
    {
        $this->authorize('delete', $linkedToType);

        try {
            $code = $linkedToType->Code;
            $linkedToType->DeletedBy = $request->user()->Id;
            $linkedToType->save();
            $linkedToType->delete();

            activity()->causedBy($request->user())->performedOn($linkedToType)
                ->event('delete')->log('deleted linked-to type ' . $code);

            return $this->succeeded("linked-to type {$code} removed successfully");
        } catch (Throwable $e) {
            Log::error('deleting (DMS) linked-to type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    private function table(): JsonResponse
    {
        $query = LinkedToType::query()->lock('WITH(NOLOCK)')->whereNull('DeletedOn');

        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('Description', fn (LinkedToType $t) => e($t->Description ?: '-'))
            ->addColumn('SourceRelation', fn (LinkedToType $t) => e(DocumentRelationOptionsController::RELATION_TYPES[$t->SourceRelation] ?? '-'))
            ->addColumn('Active', fn (LinkedToType $t) => $t->IsActive ? 'Active' : 'Inactive')
            ->addColumn('action', function (LinkedToType $t) {
                $payload = e(json_encode([
                    'Id' => $t->Id,
                    'Code' => $t->Code,
                    'Name' => $t->Name,
                    'Description' => $t->Description,
                    'SourceRelation' => $t->SourceRelation,
                    'IsActive' => (bool) $t->IsActive,
                ], JSON_THROW_ON_ERROR));

                $edit = '<button type="button" class="btn btn-sm btn-outline-primary edit-linked-to-type" '
                    . 'data-type="' . $payload . '"><i class="fas fa-edit"></i></button>';
                $delete = '<button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-linked-to-type" '
                    . 'data-id="' . $t->Id . '" data-code="' . e($t->Code) . '"><i class="fas fa-trash"></i></button>';

                return '<div class="text-nowrap">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make();
    }
}
