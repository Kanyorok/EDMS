<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\DocumentTypeRequest;
use App\Models\Core\Approval\CodeDetail;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DMSTags;
use App\Models\DMS\LinkedToType;
use App\Models\DMS\Repository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', DMSDocumentType::class);

        if ($request->ajax()) {
            try {
                return $this->table();
            } catch (Throwable $e) {
                Log::error('fetching (DMS) document types failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.settings.document-types.index', [
            'repositories' => Repository::query()->user($request->user())->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Name']),
            'tags' => DMSTags::query()->user($request->user())->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Name']),
            'entityApplicabilities' => CodeDetail::query()->where('CodeID', 'DMSEntityApplicabilityId')
                ->where('IsActive', true)->whereNull('DeletedOn')->orderBy('DisplayOrder')->get(['ID', 'Value', 'Description']),
            'linkedToTypes' => LinkedToType::query()->where('IsActive', true)->whereNull('DeletedOn')->orderBy('Name')->get(['Id', 'Code', 'Name']),
        ]);
    }

    public function store(DocumentTypeRequest $request): JsonResponse
    {
        $this->authorize('create', DMSDocumentType::class);

        try {
            $data = $request->validated();
            $linkedToTypeIds = Arr::pull($data, 'LinkedToTypeIds', []);

            $type = DMSDocumentType::create($data + [
                'CreatedBy' => $request->user()->Id,
                'ModifiedBy' => $request->user()->Id,
            ]);
            $this->syncLinkedToTypes($type, $linkedToTypeIds, $request->user()->Id);

            activity()->causedBy($request->user())->performedOn($type)
                ->event('create')->log('created document type ' . $type->Code);

            return $this->succeeded("document type {$type->Code} created successfully");
        } catch (Throwable $e) {
            Log::error('creating (DMS) document type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(DocumentTypeRequest $request, DMSDocumentType $documentType): JsonResponse
    {
        $this->authorize('update', $documentType);

        try {
            $data = $request->validated();
            $linkedToTypeIds = Arr::pull($data, 'LinkedToTypeIds', []);

            $documentType->update($data + ['ModifiedBy' => $request->user()->Id]);
            $this->syncLinkedToTypes($documentType, $linkedToTypeIds, $request->user()->Id);

            activity()->causedBy($request->user())->performedOn($documentType)
                ->event('update')->log('updated document type ' . $documentType->Code);

            return $this->succeeded("document type {$documentType->Code} updated successfully");
        } catch (Throwable $e) {
            Log::error('updating (DMS) document type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, DMSDocumentType $documentType): JsonResponse
    {
        $this->authorize('delete', $documentType);

        try {
            $code = $documentType->Code;
            $documentType->DeletedBy = $request->user()->Id;
            $documentType->save();
            $documentType->delete();

            activity()->causedBy($request->user())->performedOn($documentType)
                ->event('delete')->log('deleted document type ' . $code);

            return $this->succeeded("document type {$code} removed successfully");
        } catch (Throwable $e) {
            Log::error('deleting (DMS) document type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * Attach/detach only what actually changed, rather than sync(): sync()
     * would run every still-attached id back through updateExistingPivot(),
     * overwriting CreatedBy with the current user on a row that already had
     * a real creator. CreatedBy/ModifiedBy aren't populated automatically
     * the way CreatedOn/ModifiedOn are (those come from withTimestamps() on
     * the relation) - a newly attached id needs them passed explicitly.
     *
     * @param  array<int>  $linkedToTypeIds
     */
    private function syncLinkedToTypes(DMSDocumentType $type, array $linkedToTypeIds, int $actorId): void
    {
        $current = $type->linkedToTypes()->pluck('t_DMSLinkedToTypes.Id')->all();

        if ($toAttach = array_diff($linkedToTypeIds, $current)) {
            $type->linkedToTypes()->attach($toAttach, ['CreatedBy' => $actorId, 'ModifiedBy' => $actorId]);
        }

        if ($toDetach = array_diff($current, $linkedToTypeIds)) {
            $type->linkedToTypes()->detach($toDetach);
        }
    }

    private function table(): JsonResponse
    {
        $query = DMSDocumentType::query()
            ->lock('WITH(NOLOCK)')
            ->with(['repository', 'tag', 'linkedToTypes:Id,Code,Name'])
            ->whereNull('DeletedOn');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('Repository', fn (DMSDocumentType $t) => e($t->repository->Name ?? '-'))
            ->addColumn('Tags', fn (DMSDocumentType $t) => e($t->tag->Name ?? '-'))
            ->addColumn('LinkedToTypes', fn (DMSDocumentType $t) => $t->linkedToTypes->isNotEmpty()
                ? e($t->linkedToTypes->pluck('Code')->implode(', ')) : '-')
            ->addColumn('Extensions', fn (DMSDocumentType $t) => $t->AllowedExtensions
                ? e(collect(explode(',', $t->AllowedExtensions))->map(fn ($ext) => '.' . $ext)->implode(', '))
                : '-')
            ->addColumn('Active', fn (DMSDocumentType $t) => $t->IsActive ? 'Active' : 'Inactive')
            ->addColumn('action', function (DMSDocumentType $t) {
                $payload = e(json_encode([
                    'Id' => $t->Id,
                    'Code' => $t->Code,
                    'Name' => $t->Name,
                    'RepositoryId' => $t->RepositoryId,
                    'TagId' => $t->TagId,
                    'EntityApplicability' => $t->EntityApplicability,
                    'LinkedToTypeIds' => $t->linkedToTypes->pluck('Id'),
                    'AllowedExtensions' => $t->AllowedExtensions,
                    'MaxSizeMB' => $t->MaxSizeMB,
                    'ExpiryRequired' => (bool) $t->ExpiryRequired,
                    'IsActive' => (bool) $t->IsActive,
                ], JSON_THROW_ON_ERROR));

                $edit = '<button type="button" class="btn btn-sm btn-outline-primary edit-document-type" '
                    . 'data-type="' . $payload . '"><i class="fas fa-edit"></i></button>';
                $delete = '<button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-document-type" '
                    . 'data-id="' . $t->Id . '" data-code="' . e($t->Code) . '"><i class="fas fa-trash"></i></button>';

                return '<div class="text-nowrap">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make();
    }
}
