<?php

namespace App\Http\Controllers\DMS\LegalHold;

use App\Enums\DMS\LegalHoldStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\CreateLegalHoldRequest;
use App\Http\Requests\DMS\LockDocumentRequest;
use App\Models\Auth\User;
use App\Models\DMS\DMSTags;
use App\Models\DMS\Document;
use App\Models\DMS\LegalHold;
use App\Traits\Controller\LegalHoldTrait;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class LegalHoldController extends Controller
{
    use LegalHoldTrait;

    public function __construct()
    {
        $this->middleware('ajax')->except(['index', 'create', 'show', 'createDocument', 'documentStatus']);
    }

    /**
     * Display a listing of the resource.
     * @throws Exception
     */
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', LegalHold::class);

        return (! $request->ajax()) ? view('dms.legal-hold.index') : $this->getLegalHolds(query: LegalHold::query(), counts: ['documents']);

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CreateLegalHoldRequest $request)
    {
        $this->authorize('create', LegalHold::class);
        $documents = $request->getDocumentIds();
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($request, $documents, $actor) {
                $dated = now();
                $hold = LegalHold::create([
                    'Name' => $request->string('Name')->toString(),
                    'Ref' => $request->getRef(),
                    'Description' => $request->string('Description', '')->toString(),
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ])->refresh();
                $hold->documents()->attach($documents, [
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ], false);

                $activities = (config('activitylog.enabled')) ? collect($documents)->map(function ($document) use ($dated, $actor) {
                    return [
                        'log_name' => config('activitylog.default_log_name'),
                        'description' => 'Document added to Legal Hold ',
                        'subject_id' => $document,
                        'subject_type' => Document::getPrimaryKey(),
                        'causer_id' => $actor->Id,
                        'causer_type' => User::getPrimaryKey(),
                        'created_at' => $dated,
                        'updated_at' => $dated,
                        'event' => 'Legal Hold',
                    ];
                })->add([
                    'log_name' => config('activitylog.default_log_name'),
                    'description' => 'Created legal hold for documents',
                    'subject_id' => $hold->Id,
                    'subject_type' => LegalHold::getPrimaryKey(),
                    'causer_id' => $actor->Id,
                    'causer_type' => User::getPrimaryKey(),
                    'created_at' => $dated,
                    'updated_at' => $dated,
                    'event' => 'Create',
                ]) : collect();

                if ($activities->isNotEmpty()) {
                    DB::table(config('activitylog.table_name'))->insert($activities->toArray());
                }

                return $this->succeeded('Legal Hold created successfully');
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error creating legal hold: ' . $e);

            return $this->errored('an error occurred while creating legal hold');
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $this->authorize('create', LegalHold::class);

        return view('dms.legal-hold.create')->with('tags', DMSTags::query()->user($request->user())->get(['t_DMSTags.TagID', 't_DMSTags.Name']));
    }

    /**
     * Show the form for locking/unlocking one specific document.
     */
    public function createDocument(Request $request): View
    {
        $this->authorize('create', LegalHold::class);

        return view('dms.legal-hold.create-document');
    }

    /**
     * Current legal-hold status for one document. A hold is only reported as releasable from
     * this screen when it covers this document alone - a shared/tag-based hold could freeze
     * other documents too, and unlocking here has no business lifting those.
     */
    public function documentStatus(Request $request): JsonResponse
    {
        $this->authorize('create', LegalHold::class);
        $request->validate(['DocumentId' => ['required', 'string', 'exists:t_Documents,DocumentId']]);

        $document = Document::where('DocumentId', $request->string('DocumentId')->toString())->firstOrFail();

        $holds = $document->holds()->where('Status', LegalHoldStatusEnum::Active->value)->withCount('documents')->get();

        return response()->json([
            'DocumentId' => $document->DocumentId,
            'Name' => $document->Name,
            'isHold' => $holds->isNotEmpty(),
            'holds' => $holds->map(fn (LegalHold $hold) => [
                'Ref' => $hold->Ref,
                'Name' => $hold->Name,
                'documentsCount' => $hold->documents_count,
                'releasable' => $hold->documents_count === 1,
            ]),
        ]);
    }

    /**
     * Puts a single document on its own dedicated legal hold, independent of any tag.
     */
    public function lockDocument(LockDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', LegalHold::class);
        $document = $request->getDocument();
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($request, $document, $actor) {
                $dated = now();
                $hold = LegalHold::create([
                    'Name' => 'Document Hold: ' . $document->Name,
                    'Ref' => $request->getRef(),
                    'Description' => $request->string('Description', '')->toString(),
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ])->refresh();

                $hold->documents()->attach([$document->Id], [
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ], false);

                if (config('activitylog.enabled')) {
                    DB::table(config('activitylog.table_name'))->insert([
                        [
                            'log_name' => config('activitylog.default_log_name'),
                            'description' => 'Document added to Legal Hold ',
                            'subject_id' => $document->Id,
                            'subject_type' => Document::getPrimaryKey(),
                            'causer_id' => $actor->Id,
                            'causer_type' => User::getPrimaryKey(),
                            'created_at' => $dated,
                            'updated_at' => $dated,
                            'event' => 'Legal Hold',
                        ],
                        [
                            'log_name' => config('activitylog.default_log_name'),
                            'description' => 'Created legal hold for document ' . $document->Name,
                            'subject_id' => $hold->Id,
                            'subject_type' => LegalHold::getPrimaryKey(),
                            'causer_id' => $actor->Id,
                            'causer_type' => User::getPrimaryKey(),
                            'created_at' => $dated,
                            'updated_at' => $dated,
                            'event' => 'Create',
                        ],
                    ]);
                }

                return $this->succeeded('document locked successfully');
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error locking document: ' . $e);

            return $this->errored('an error occurred while locking the document');
        }
    }

    /**
     * Releases every dedicated (single-document) hold currently covering this document. A hold
     * shared with other documents is left alone - deliberately not releasable from here.
     */
    public function unlockDocument(Request $request): JsonResponse
    {
        $this->authorize('create', LegalHold::class);
        $request->validate(['DocumentId' => ['required', 'string', 'exists:t_Documents,DocumentId']]);

        $document = Document::where('DocumentId', $request->string('DocumentId')->toString())->firstOrFail();
        $actor = $request->user();

        $holds = $document->holds()->where('Status', LegalHoldStatusEnum::Active->value)->withCount('documents')->get()
            ->filter(fn (LegalHold $hold) => $hold->documents_count === 1);

        if ($holds->isEmpty()) {
            return $this->errored('this document has no dedicated hold to release here - it may still be on hold via a shared Tag Based Hold.');
        }

        foreach ($holds as $hold) {
            $this->authorize('release', $hold);
        }

        try {
            return DB::transaction(function () use ($holds, $document, $actor) {
                $dated = now();
                foreach ($holds as $hold) {
                    $hold->update([
                        'Status' => LegalHoldStatusEnum::Released,
                        'ReleasedOn' => $dated,
                        'ReleasedBy' => $actor->Id,
                    ]);

                    if (config('activitylog.enabled')) {
                        DB::table(config('activitylog.table_name'))->insert([
                            [
                                'log_name' => config('activitylog.default_log_name'),
                                'description' => 'Legal Hold (' . $hold->Ref . ') Released',
                                'subject_id' => $document->Id,
                                'subject_type' => Document::getPrimaryKey(),
                                'causer_id' => $actor->Id,
                                'causer_type' => User::getPrimaryKey(),
                                'created_at' => $dated,
                                'updated_at' => $dated,
                                'event' => 'Legal Hold',
                            ],
                            [
                                'log_name' => config('activitylog.default_log_name'),
                                'description' => 'legal hold released',
                                'subject_id' => $hold->Id,
                                'subject_type' => LegalHold::getPrimaryKey(),
                                'causer_id' => $actor->Id,
                                'causer_type' => User::getPrimaryKey(),
                                'created_at' => $dated,
                                'updated_at' => $dated,
                                'event' => 'Released',
                            ],
                        ]);
                    }
                }

                return $this->succeeded('document unlocked successfully');
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error unlocking document: ' . $e);

            return $this->errored('an error occurred while unlocking the document');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LegalHold $dMSLegalHold)
    {
        $this->authorize('view', $dMSLegalHold);

        return view('dms.legal-hold.show')
            ->with('hold', $dMSLegalHold->loadCount('documents'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LegalHold $dMSLegalHold)
    {
        $request->validate([
            'Name' => 'required|string|max:255',
            'Description' => 'nullable|string|max:5000',
        ]);

        $actor = $request->user();

        try {
            return DB::transaction(function () use ($request, $dMSLegalHold, $actor) {
                $dMSLegalHold->forceFill([
                    'Name' => $request->string('Name')->trim()->toString(),
                    'Description' => $request->string('Description', '')->trim()->toString(),
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($dMSLegalHold)->event('update')->log('updated ' . $dMSLegalHold->Ref . ' legal hold.');

                return $this->succeeded('Legal Hold updated successfully', route('legal-hold.show', [$dMSLegalHold->Ref]));
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error updating legal hold: ' . $e);

            return $this->errored('an error occurred while updating legal hold');
        }
    }

    /**
     * Cancel
     */
    public function destroy(Request $request, LegalHold $dMSLegalHold)
    {
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($dMSLegalHold, $actor) {
                $dMSLegalHold->update([
                    'Status' => LegalHoldStatusEnum::Canceled,
                    'ReleasedOn' => now(),
                    'ReleasedBy' => $actor->Id,
                ]);


                $documents = $dMSLegalHold->documents()->get(['DocId'])->pluck('DocId')->toArray();
                $dated = now();
                $activities = (config('activitylog.enabled')) ? collect($documents)->map(function ($document) use ($dMSLegalHold, $dated, $actor) {
                    return [
                        'log_name' => config('activitylog.default_log_name'),
                        'description' => 'Legal Hold (' . $dMSLegalHold->Ref . ') Canceled',
                        'subject_id' => $document,
                        'subject_type' => Document::getPrimaryKey(),
                        'causer_id' => $actor->Id,
                        'causer_type' => User::getPrimaryKey(),
                        'created_at' => $dated,
                        'updated_at' => $dated,
                        'event' => 'Legal Hold',
                    ];
                })->add([
                    'log_name' => config('activitylog.default_log_name'),
                    'description' => 'Canceled legal hold for documents',
                    'subject_id' => $dMSLegalHold->Id,
                    'subject_type' => LegalHold::getPrimaryKey(),
                    'causer_id' => $actor->Id,
                    'causer_type' => User::getPrimaryKey(),
                    'created_at' => $dated,
                    'updated_at' => $dated,
                    'event' => 'Canceled',
                ]) : collect();

                if ($activities->isNotEmpty()) {
                    DB::table(config('activitylog.table_name'))->insert($activities->toArray());
                }

                return $this->succeeded('Legal Hold canceled successfully', route('legal-hold.show', [$dMSLegalHold->Ref]));
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error canceling legal hold: ' . $e);

            return $this->errored('an error occurred while canceling legal hold');
        }
    }

    /**
     * release
     */
    public function release(Request $request, LegalHold $dMSLegalHold)
    {
        $actor = $request->user();

        try {
            return DB::transaction(function () use ($dMSLegalHold, $actor) {
                $dMSLegalHold->update([
                    'Status' => LegalHoldStatusEnum::Released,
                    'ReleasedOn' => now(),
                    'ReleasedBy' => $actor->Id,
                ]);


                $documents = $dMSLegalHold->documents()->get(['DocId'])->pluck('DocId')->toArray();
                $dated = now();
                $activities = (config('activitylog.enabled')) ? collect($documents)->map(function ($document) use ($dMSLegalHold, $dated, $actor) {
                    return [
                        'log_name' => config('activitylog.default_log_name'),
                        'description' => 'Legal Hold (' . $dMSLegalHold->Ref . ') Released',
                        'subject_id' => $document,
                        'subject_type' => Document::getPrimaryKey(),
                        'causer_id' => $actor->Id,
                        'causer_type' => User::getPrimaryKey(),
                        'created_at' => $dated,
                        'updated_at' => $dated,
                        'event' => 'Legal Hold',
                    ];
                })->add([
                    'log_name' => config('activitylog.default_log_name'),
                    'description' => 'legal hold released',
                    'subject_id' => $dMSLegalHold->Id,
                    'subject_type' => LegalHold::getPrimaryKey(),
                    'causer_id' => $actor->Id,
                    'causer_type' => User::getPrimaryKey(),
                    'created_at' => $dated,
                    'updated_at' => $dated,
                    'event' => 'Released',
                ]) : collect();

                if ($activities->isNotEmpty()) {
                    DB::table(config('activitylog.table_name'))->insert($activities->toArray());
                }

                return $this->succeeded('Legal Hold released successfully', route('legal-hold.show', [$dMSLegalHold->Ref]));
            });
        } catch (Throwable | Exception $e) {
            Log::error('Error releasing legal hold: ' . $e);

            return $this->errored('an error occurred while releasing legal hold');
        }
    }
}
