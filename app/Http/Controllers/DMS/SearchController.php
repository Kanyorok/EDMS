<?php

namespace App\Http\Controllers\DMS;

use App\Enums\Core\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\DMS\FilesCollection;
use App\Http\Resources\DMS\RepositoryCollection;
use App\Models\DMS\Document;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\Repository;
use App\Services\DMS\RegistryAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly RegistryAccessService $access)
    {
        $this->middleware('ajax');
    }

    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $actor = $request->user();
        $search = $request->get('q', '');
        $search = (is_string($search)) ? str_replace(['*', '%'], ['', ''], $search) : '';
        $search = trim($search);

        if ($request->type === 'files') {
            if (empty($search)) {
                return new FilesCollection(collect([]));
            }
            $files = Document::query()->user($actor)->whereHas('current')->where(function (Builder $query) use ($search) {
                $query->where('t_Documents.Name', 'LIKE', "%{$search}%")
                    ->orWhereHas('current', function (Builder $q) use ($search) {
                        $q->where('t_DocumentVersions.Name', 'LIKE', "%{$search}%")
                            ->orWhere('t_DocumentVersions.Blob', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('tags', function (Builder $q) use ($search) {
                        $q->where('t_DMSTags.Name', 'LIKE', "%{$search}%");
                    });
            })->with(['current', 'repository'])->limit(5)->get();

            return (new FilesCollection($files))->setMinified(true);
        }
        if ($request->type === 'repositories') {
            if (empty($search)) {
                return new RepositoryCollection(collect([]));
            }

            $repos = Repository::query()->user($actor)->where(function (Builder $query) use ($search) {
                $query->where('t_Repositories.Name', 'LIKE', "%{$search}%")
                    ->Orwhere('t_Repositories.Description', 'LIKE', "%{$search}%");
            })->limit(5)->get();

            return (new RepositoryCollection($repos))->setMinified(true);
        }
        if ($request->type === 'physical-files') {
            return $this->physicalFiles($request, $search);
        }

        return $this->errored('Search functionality is not implemented yet.', status: 501);
    }

    /**
     * Paper files matching the term, for the global search bar.
     *
     * Three gates apply, the same three that guard the registry workspace:
     * the DMS view permission, the branch that custodies the registry, and -
     * for anyone who cannot manage registries - the file's own visibility.
     */
    private function physicalFiles(Request $request, string $search): JsonResponse
    {
        $actor = $request->user();

        if ($search === '' || ! $actor->can(PermissionEnum::DMSView->value)) {
            return response()->json(['data' => []]);
        }

        $canManage = $actor->can(PermissionEnum::DMSRegistryManage->value)
            || $actor->can(PermissionEnum::Admin->value);

        $files = PhysicalFile::query()
            ->when(! $canManage, fn (Builder $q) => $q->user($actor))
            ->whereIn('HomeRegistryId', $this->access->scope($actor)->select('Id'))
            ->whereNull('DeletedOn')
            ->where(function (Builder $query) use ($search) {
                $query->where('FileNumber', 'LIKE', "%{$search}%")
                    ->orWhere('Title', 'LIKE', "%{$search}%")
                    ->orWhere('Barcode', 'LIKE', "%{$search}%")
                    ->orWhereHas('documents', function (Builder $q) use ($search) {
                        $q->where('Title', 'LIKE', "%{$search}%");
                    });
            })
            ->with(['homeRegistry', 'homeLocation', 'status'])
            ->withCount('documents')
            ->orderBy('FileNumber')
            ->limit(5)
            ->get();

        return response()->json([
            'data' => $files->map(fn (PhysicalFile $file) => [
                'id' => $file->Id,
                'name' => $file->FileNumber,
                'value' => $file->FileNumber . ' ' . $file->Title,
                'title' => $file->Title,
                'status' => $file->status?->Description ?: '-',
                'documents' => (int) $file->documents_count,
                'location' => trim((string) $file->homeLocation?->Path, '/')
                    ?: ($file->homeRegistry?->Name ?: '-'),
                'links' => [
                    'detail' => route('registry.physical-files.show', $file->Id),
                ],
            ])->values(),
        ]);
    }
}
