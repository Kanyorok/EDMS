<?php

namespace App\Http\Controllers\DMS\Settings;

use App\Enums\Core\DataTypesEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\DMS\EventTypeRequest;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DocumentValidationType;
use App\Models\DMS\EventType;
use App\Models\DMS\LinkedToType;
use App\Models\DMS\SourceSystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;
use Yajra\DataTables\DataTables;

class EventTypeController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', EventType::class);

        if ($request->ajax()) {
            try {
                return $this->table();
            } catch (Throwable $e) {
                Log::error('fetching (DMS) event types failed : ' . $e);

                return $this->errored('fetching data failed, try again later');
            }
        }

        return view('dms.settings.event-types.index', [
            'sourceSystems' => SourceSystem::query()->where('IsActive', true)->orderBy('Name')->get(['Id', 'Code', 'Name']),
            'documentTypes' => DMSDocumentType::query()->with('linkedToTypes')->where('IsActive', true)->orderBy('Name')->get(),
            'linkedToTypes' => LinkedToType::query()->where('IsActive', true)->orderBy('Name')->get(['Id', 'Code', 'Name']),
            'dataTypes' => self::dataTypeLabels(),
            'users' => User::query()->orderBy('Name')->get(['Id', 'Name']),
            'teams' => Team::query()->orderBy('Name')->get(['TeamID', 'Name']),
            'validationTypes' => DocumentValidationType::query()->orderBy('Name')->get(['Id', 'ValidationTypeId', 'Name']),
        ]);
    }

    public function store(EventTypeRequest $request): JsonResponse
    {
        $this->authorize('create', EventType::class);

        try {
            $data = $request->validated();
            $modules = Arr::pull($data, 'Modules', []);
            $properties = Arr::pull($data, 'Properties', []);
            $documentTypeIds = Arr::pull($data, 'DocumentTypeIds', []);
            $actorId = $request->user()->Id;

            $event = DB::transaction(function () use ($data, $modules, $properties, $documentTypeIds, $actorId) {
                $event = EventType::create($data + ['CreatedBy' => $actorId, 'ModifiedBy' => $actorId]);
                $this->syncDocumentTypes($event, $documentTypeIds, $actorId);
                $this->syncModules($event, $modules, $actorId);
                $this->syncProperties($event, $properties, $actorId);

                return $event;
            });

            activity()->causedBy($request->user())->performedOn($event)
                ->event('create')->log('created event type ' . $event->Code);

            return $this->succeeded("event type {$event->Code} created successfully");
        } catch (Throwable $e) {
            Log::error('creating (DMS) event type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function update(EventTypeRequest $request, EventType $eventType): JsonResponse
    {
        $this->authorize('update', $eventType);

        try {
            $data = $request->validated();
            $modules = Arr::pull($data, 'Modules', []);
            $properties = Arr::pull($data, 'Properties', []);
            $documentTypeIds = Arr::pull($data, 'DocumentTypeIds', []);
            $actorId = $request->user()->Id;

            DB::transaction(function () use ($eventType, $data, $modules, $properties, $documentTypeIds, $actorId) {
                $eventType->update($data + ['ModifiedBy' => $actorId]);
                $this->syncDocumentTypes($eventType, $documentTypeIds, $actorId);
                $this->syncModules($eventType, $modules, $actorId);
                $this->syncProperties($eventType, $properties, $actorId);
            });

            activity()->causedBy($request->user())->performedOn($eventType)
                ->event('update')->log('updated event type ' . $eventType->Code);

            return $this->succeeded("event type {$eventType->Code} updated successfully");
        } catch (Throwable $e) {
            Log::error('updating (DMS) event type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    public function destroy(Request $request, EventType $eventType): JsonResponse
    {
        $this->authorize('delete', $eventType);

        // Documents filed under the event keep pointing at it, so it can be switched off
        // (IsActive) but not removed once anything was uploaded through it.
        if (DB::table('t_Documents')->where('EventTypeId', $eventType->Id)->exists()) {
            return $this->errored("event type {$eventType->Code} already has documents; deactivate it instead of removing it");
        }

        try {
            $code = $eventType->Code;

            DB::transaction(function () use ($eventType, $request) {
                $eventType->documentTypes()->detach();
                $eventType->modules()->delete();
                $eventType->properties()->delete();
                $eventType->DeletedBy = $request->user()->Id;
                $eventType->save();
                $eventType->delete();
            });

            activity()->causedBy($request->user())->performedOn($eventType)
                ->event('delete')->log('deleted event type ' . $code);

            return $this->succeeded("event type {$code} removed successfully");
        } catch (Throwable $e) {
            Log::error('deleting (DMS) event type failed : ' . $e);
        }

        return $this->errored('an unexpected error occurred, try again later');
    }

    /**
     * The document types the event offers. Adding or removing one changes what CBS's "what is this document?" lists;
     * documents already filed keep the type they were filed under.
     *
     * @param array<int, int|string> $documentTypeIds
     */
    private function syncDocumentTypes(EventType $event, array $documentTypeIds, int $actorId): void
    {
        $event->documentTypes()->sync(
            collect($documentTypeIds)->mapWithKeys(fn ($id) => [(int) $id => ['CreatedBy' => $actorId, 'ModifiedBy' => $actorId]])->all()
        );
    }

    /**
     * @param array<int, int> $moduleIds
     */
    private function syncModules(EventType $event, array $moduleIds, int $actorId): void
    {
        $wanted = collect($moduleIds)->map(fn ($id) => (int) $id)->unique()->values();

        $event->modules()->whereNotIn('SourceModuleId', $wanted)->delete();

        $existing = $event->modules()->pluck('SourceModuleId');
        foreach ($wanted->diff($existing) as $moduleId) {
            $event->modules()->create([
                'SourceModuleId' => $moduleId,
                'CreatedBy' => $actorId,
                'ModifiedBy' => $actorId,
            ]);
        }
    }

    /**
     * Rebuilds the property list in the order submitted. Matched on Name so that a
     * property that keeps its name keeps its row (and its Id) across edits.
     *
     * @param array<int, array<string, mixed>> $properties
     */
    private function syncProperties(EventType $event, array $properties, int $actorId): void
    {
        $names = collect($properties)->pluck('Name');
        $event->properties()->whereNotIn('Name', $names)->delete();

        foreach (array_values($properties) as $order => $property) {
            $values = [
                'Label' => $property['Label'],
                'DataType' => $property['DataType'],
                'IsRequired' => (bool) ($property['IsRequired'] ?? false),
                'Options' => $property['Options'] ?? null,
                'SortOrder' => $order,
                'ModifiedBy' => $actorId,
            ];

            $row = $event->properties()->where('Name', $property['Name'])->first();
            if ($row) {
                $row->update($values);
            } else {
                $event->properties()->create($values + [
                    'Name' => $property['Name'],
                    'CreatedBy' => $actorId,
                ]);
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public static function dataTypeLabels(): array
    {
        return [
            DataTypesEnum::String->value => 'Text',
            DataTypesEnum::Integer->value => 'Whole number',
            DataTypesEnum::Float->value => 'Decimal number',
            DataTypesEnum::Boolean->value => 'Yes / No',
            DataTypesEnum::DateTime->value => 'Date & time',
            DataTypesEnum::Time->value => 'Time',
            DataTypesEnum::List->value => 'Pick from list',
        ];
    }

    private function table(): JsonResponse
    {
        $query = EventType::query()->lock('WITH(NOLOCK)')
            ->with(['sourceSystem:Id,Code,Name', 'documentTypes', 'modules:Id,EventTypeId,SourceModuleId', 'properties'])
            ->whereNull('DeletedOn');

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('System', fn (EventType $e) => e($e->sourceSystem?->Name ?? '-'))
            ->addColumn('DocumentTypes', fn (EventType $e) => e($e->documentTypes->pluck('Name')->sort()->implode(', ') ?: '-'))
            ->addColumn('Modules', fn (EventType $e) => e($e->modules->pluck('SourceModuleId')->sort()->implode(', ') ?: '-'))
            ->addColumn('PropertyCount', fn (EventType $e) => $e->properties->count())
            ->addColumn('Active', fn (EventType $e) => $e->IsActive ? 'Active' : 'Inactive')
            ->addColumn('action', function (EventType $e) {
                $payload = e(json_encode([
                    'Id' => $e->Id,
                    'SourceSystemId' => $e->SourceSystemId,
                    'Code' => $e->Code,
                    'Name' => $e->Name,
                    'Description' => $e->Description,
                    'DocumentTypeIds' => $e->documentTypes->pluck('Id')->values(),
                    'LinkedToTypeId' => $e->LinkedToTypeId,
                    'ExternalEntityType' => $e->ExternalEntityType,
                    'AllowReplace' => (bool) $e->AllowReplace,
                    'ReviewerUserId' => $e->ReviewerUserId,
                    'ReviewerTeamId' => $e->ReviewerTeamId,
                    'ValidationTypeId' => $e->ValidationTypeId,
                    'IsActive' => (bool) $e->IsActive,
                    'Modules' => $e->modules->pluck('SourceModuleId')->sort()->values(),
                    'Properties' => $e->properties->map(fn ($p) => [
                        'Name' => $p->Name,
                        'Label' => $p->Label,
                        'DataType' => $p->DataType->value,
                        'IsRequired' => (bool) $p->IsRequired,
                        'Options' => $p->Options,
                    ])->values(),
                ], JSON_THROW_ON_ERROR));

                $edit = '<button type="button" class="btn btn-sm btn-outline-primary edit-event-type" '
                    . 'data-event="' . $payload . '"><i class="fas fa-edit"></i></button>';
                $delete = '<button type="button" class="btn btn-sm btn-outline-danger ms-1 delete-event-type" '
                    . 'data-id="' . $e->Id . '" data-code="' . e($e->Code) . '"><i class="fas fa-trash"></i></button>';

                return '<div class="text-nowrap">' . $edit . $delete . '</div>';
            })
            ->rawColumns(['action'])
            ->make();
    }
}
