<?php

namespace App\Services\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Models\DMS\EventType;
use App\Models\DMS\SourceSystem;
use Illuminate\Support\Collection;

/**
 * What an external system may upload from a given screen: the events mapped to its
 * module, each with the document type rules and the form definition it renders.
 *
 * A source system only ever sees its own active events, and only those whose document
 * type is still active, so switching either off takes an event out of the catalog at once.
 */
class EventCatalogService
{
    /**
     * Wire names for DataTypesEnum. The external system is told these, never the enum's
     * internal two-letter codes.
     */
    private const array TYPE_NAMES = [
        'st' => 'string',
        'it' => 'integer',
        'ft' => 'float',
        'bo' => 'boolean',
        'dt' => 'datetime',
        'tm' => 'time',
        'ls' => 'list',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forModule(SourceSystem $system, int $moduleId, ?string $externalEntityType = null): array
    {
        if (! $system->IsActive) {
            return [];
        }

        return $this->activeEvents($system)
            ->whereHas('modules', fn ($q) => $q->where('SourceModuleId', $moduleId))
            ->when($externalEntityType, fn ($q) => $q->where('ExternalEntityType', strtoupper($externalEntityType)))
            ->get()
            ->map(fn (EventType $event) => $this->present($event))
            ->values()
            ->all();
    }

    /**
     * The event a system is allowed to raise from a module, or null. Used to re-check on
     * upload what the catalog offered, since the form cannot be trusted to have stayed within it.
     */
    public function resolve(SourceSystem $system, string $eventCode, int $moduleId): ?EventType
    {
        if (! $system->IsActive) {
            return null;
        }

        return $this->activeEvents($system)
            ->where('Code', $eventCode)
            ->whereHas('modules', fn ($q) => $q->where('SourceModuleId', $moduleId))
            ->first();
    }

    private function activeEvents(SourceSystem $system)
    {
        return EventType::query()
            ->with([
                // Only document types that are still switched on are offered; the ones an event lists but an admin
                // has since deactivated simply drop out of the choice.
                'documentTypes' => fn ($q) => $q->where('t_DMSDocumentTypes.IsActive', true),
                'properties', 'modules', 'reviewerUser', 'reviewerTeam', 'validationType',
            ])
            ->where('SourceSystemId', $system->Id)
            ->where('IsActive', true)
            ->whereHas('documentTypes', fn ($q) => $q->where('t_DMSDocumentTypes.IsActive', true))
            ->orderBy('Name');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(EventType $event): array
    {
        return [
            'code' => $event->Code,
            'name' => $event->Name,
            'description' => $event->Description,
            'externalEntityType' => $event->ExternalEntityType,
            'allowReplace' => (bool) $event->AllowReplace,
            // A review can always be asked for if the caller names a reviewer; "default" says whether
            // one is set on the event, so a screen knows whether it must ask.
            'review' => [
                'defaultReviewer' => $event->reviewerUser?->Name ?? $event->reviewerTeam?->Name,
                'defaultKind' => $event->reviewerUser ? 'user' : ($event->reviewerTeam ? 'team' : null),
            ],
            'validation' => [
                'available' => $event->validationType !== null,
                'type' => $event->validationType?->Name,
            ],
            // What the person is filing: each type carries its own file rules, so the form checks the chosen one.
            'documentTypes' => $event->documentTypes
                ->sortBy('Name')
                ->map(fn ($type) => [
                    'code' => $type->Code,
                    'name' => $type->Name,
                    'allowedExtensions' => $type->extensions()->values()->all(),
                    'maxSizeMB' => $type->MaxSizeMB,
                    'expiryRequired' => (bool) $type->ExpiryRequired,
                ])
                ->values()
                ->all(),
            'properties' => $event->properties
                ->map(fn ($property) => [
                    'name' => $property->Name,
                    'label' => $property->Label,
                    'type' => self::TYPE_NAMES[$property->DataType->value] ?? 'string',
                    'required' => (bool) $property->IsRequired,
                    'options' => $property->DataType === DataTypesEnum::List ? array_values($property->Options ?? []) : null,
                ])
                ->values()
                ->all(),
        ];
    }
}
