<?php

namespace App\Http\Controllers\DMS\Files;

use App\Enums\DMS\DocumentCheckOutStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentVersion;
use App\Services\DMS\DocumentService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class DocumentVersionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('ajax');
    }

    /**
     * Display the version history of the specified document, newest first.
     */
    public function index(Document $document): View
    {
        $this->authorize('view', $document);

        $versions = $document->versions()->with('creator')->orderByDesc('Id')->get();
        $this->attachCheckoutCycles($document, $versions);

        return view('dms.files.versions')
            ->with('file', $document)
            ->with('versions', $versions);
    }

    /**
     * Preview the file as it existed at a specific version.
     */
    public function preview(Document $document, DocumentVersion $version): View
    {
        $this->authorize('view', $document);
        abort_unless($version->DocumentId === $document->Id, 404);

        $service = new DocumentService($document);
        $type = $service->typeOf($version);
        $previewable = $type->isPreview();

        return view('dms.files.version-preview')
            ->with('file', $document)
            ->with('version', $version)
            ->with('type', $type)
            ->with('previewable', $previewable)
            ->with('html', $previewable ? $service->previewVersion($version, 'width="100%" height="100" style="min-height:60vh;" class="img-fluid"') : '')
            ->with('downloadUrl', URL::temporarySignedRoute('dms.file.version-raw', now()->addMinutes(15), ['document' => $document->DocumentId, 'version' => $version->Id]));
    }

    /**
     * Correlate each version with the check-out/check-in cycle that produced it. Check-ins
     * happen strictly in sequence (a new check-out cannot start while one is active), and the
     * check-in update + new version row are written in the same DB transaction, so the nearest
     * unclaimed check-in within a few seconds of a version's CreatedOn is that version's
     * originating cycle. There is no FK linking the two tables, so this is the only way to
     * surface the check-out/check-in notes against the version they produced.
     */
    private function attachCheckoutCycles(Document $document, Collection $versions): void
    {
        $checkIns = $document->checkouts()
            ->where('Status', DocumentCheckOutStatusEnum::CheckIn->value)
            ->whereNotNull('Dated')
            ->orderBy('Dated')
            ->get();

        $usedIds = [];
        foreach ($versions->sortBy('Id') as $version) {
            $match = $checkIns->first(function ($checkOut) use ($version, $usedIds) {
                return ! in_array($checkOut->Id, $usedIds, true)
                    && abs($checkOut->Dated->diffInSeconds($version->CreatedOn)) <= 30;
            });
            if ($match) {
                $usedIds[] = $match->Id;
                $version->setRelation('checkoutCycle', $match);
            }
        }
    }
}
