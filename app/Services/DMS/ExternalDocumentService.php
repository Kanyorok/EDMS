<?php

namespace App\Services\DMS;

use App\Enums\DMS\DocumentReviewStatusEnum;
use App\Enums\DMS\DocumentValidationModeEnum;
use App\Enums\DMS\DocumentValidationStatusEnum;
use App\Enums\DMS\LegalHoldStatusEnum;
use App\Exceptions\ErroredException;
use App\Models\Auth\User;
use App\Models\DMS\Document;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DocumentAttribute;
use App\Models\DMS\DocumentReview;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\EventType;
use App\Models\DMS\SourceSystem;
use App\Services\CRMEmailService;
use App\Services\DMS\Verification\DocumentValidationWorkflow;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * What an external system (core banking today) does with documents: file one against a
 * record of its own, list what is filed against a record, replace the file, and read a
 * document's state and content.
 *
 * Everything the external system does is attributed to the source system's service user;
 * the person who acted over there is kept as source.* document attributes and in the
 * activity log. Every rule DMS applies to its own users (legal hold, review, archive,
 * checkout, validation) is applied here too, through DocumentService.
 */
class ExternalDocumentService
{
    public const string CONTENT_ROUTE = 'dms.external.content';
    public const int LINK_MINUTES = 5;

    /**
     * @param array<int, array{Name: string, Value: string, DataType: string}> $properties already validated
     *
     * @throws ErroredException
     */
    public function ingest(
        SourceSystem $system,
        EventType $event,
        DMSDocumentType $documentType,
        int $moduleId,
        string $entityId,
        UploadedFile $file,
        string $actorId,
        ?string $actorName,
        array $properties,
        ?Carbon $expiryDate = null,
        ?string $notes = null,
    ): Document {
        $repository = $documentType->repository;
        if ($repository === null) {
            throw new ErroredException("document type {$documentType->Code} has no repository to file into.");
        }

        $serviceUser = $system->serviceUser;

        return DB::transaction(function () use ($system, $event, $moduleId, $entityId, $file, $actorId, $actorName, $properties, $expiryDate, $notes, $documentType, $repository, $serviceUser) {
            $service = DocumentService::createUpload($repository, $file, $serviceUser, true, [
                'DocumentTypeId' => $documentType->Id,
                'LinkedToTypeId' => $this->linkedToTypeFor($event, $documentType),
                'ExpiryDate' => $expiryDate?->toDateString(),
                'Notes' => $notes,
            ]);

            $document = $service->document;
            $document->update([
                'SourceSystemId' => $system->Id,
                'EventTypeId' => $event->Id,
                'SourceModuleId' => $moduleId,
                'ExternalEntityType' => $event->ExternalEntityType,
                'ExternalEntityId' => $entityId,
                'ModifiedBy' => $serviceUser->Id,
            ]);

            $this->storeAttributes($document, $serviceUser, array_merge($properties, $this->sourceAttributes($system, $event->Code, $moduleId, $actorId, $actorName)));

            activity()->causedBy($serviceUser)->performedOn($document)->event('upload')
                ->log("Uploaded from {$system->Code} by {$actorId} against {$event->ExternalEntityType} {$entityId} ({$event->Code})");

            return $document->refresh();
        });
    }

    /**
     * The event's linked-to type when the chosen document type allows it, otherwise that document type's own first
     * one (a type restricts which records it may be linked to), otherwise none.
     */
    private function linkedToTypeFor(EventType $event, DMSDocumentType $documentType): ?int
    {
        $allowed = $documentType->linkedToTypes()->pluck('t_DMSLinkedToTypes.Id');

        if ($event->LinkedToTypeId && ($allowed->isEmpty() || $allowed->contains($event->LinkedToTypeId))) {
            return (int) $event->LinkedToTypeId;
        }

        return $allowed->isNotEmpty() ? (int) $allowed->first() : null;
    }

    /**
     * @throws ErroredException
     */
    public function replace(SourceSystem $system, Document $document, UploadedFile $file, string $actorId, ?string $actorName, ?string $remark = null): Document
    {
        $event = $document->eventType;
        if (! $event?->AllowReplace) {
            throw new ErroredException('this kind of document cannot be replaced from ' . $system->Code . '.');
        }

        $serviceUser = $system->serviceUser;

        DB::transaction(function () use ($system, $document, $file, $actorId, $actorName, $remark, $serviceUser, $event) {
            (new DocumentService($document))->newVersionExternal($file, $serviceUser, (string) $remark);

            $document->refresh();
            $this->storeAttributes($document, $serviceUser, $this->sourceAttributes($system, $event->Code, (int) $document->SourceModuleId, $actorId, $actorName, replaced: true));

            activity()->causedBy($serviceUser)->performedOn($document)->event('replace')
                ->log("Replaced from {$system->Code} by {$actorId}");
        });

        return $document->refresh();
    }

    /**
     * Documents filed against one external record, newest first.
     *
     * @return Collection<int, Document>
     */
    public function forEntity(SourceSystem $system, string $entityType, string $entityId, ?string $eventCode = null, ?int $moduleId = null): Collection
    {
        return Document::query()
            ->with(['current', 'eventType', 'documentType', 'properties'])
            ->where('SourceSystemId', $system->Id)
            ->where('ExternalEntityType', strtoupper($entityType))
            ->where('ExternalEntityId', $entityId)
            ->when($eventCode, fn ($q) => $q->whereHas('eventType', fn ($e) => $e->where('Code', $eventCode)))
            ->when($moduleId, fn ($q) => $q->where('SourceModuleId', $moduleId))
            ->whereHas('current')
            ->orderByDesc('Id')
            ->get();
    }

    public function find(SourceSystem $system, string $documentId): ?Document
    {
        return Document::query()
            ->with(['current', 'eventType', 'documentType', 'properties'])
            ->where('SourceSystemId', $system->Id)
            ->where('DocumentId', $documentId)
            ->first();
    }

    /**
     * A short-lived link to the document's current content. The actor is part of the signed
     * URL so it cannot be swapped, and is what the view is logged against.
     *
     * @return array{url: string, expiresAt: string}
     */
    public function contentLink(Document $document, string $actorId): array
    {
        $expires = now()->addMinutes(self::LINK_MINUTES);

        return [
            'url' => URL::temporarySignedRoute(self::CONTENT_ROUTE, $expires, ['documentId' => $document->DocumentId, 'actor' => $actorId]),
            'expiresAt' => $expires->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ErroredException
     */
    public function present(Document $document): array
    {
        $service = new DocumentService($document);
        $event = $document->eventType;
        $current = $document->current;
        $lockReason = $service->lockReason();
        $names = $event?->properties->pluck('Name') ?? collect();

        return [
            'documentId' => $document->DocumentId,
            'name' => $document->Name,
            'mimeType' => $document->MimeType,
            'event' => $event ? ['code' => $event->Code, 'name' => $event->Name] : null,
            'documentType' => $document->documentType ? ['code' => $document->documentType->Code, 'name' => $document->documentType->Name] : null,
            'entity' => ['type' => $document->ExternalEntityType, 'id' => $document->ExternalEntityId],
            'module' => $document->SourceModuleId,
            'version' => $current ? (int) $current->Version : null,
            'size' => $current?->Size,
            'uploadedOn' => $document->CreatedOn?->toIso8601String(),
            'modifiedOn' => $document->ModifiedOn?->toIso8601String(),
            'expiryDate' => $document->ExpiryDate?->toDateString(),
            'status' => $this->status($document, $service, $lockReason, (bool) $event?->AllowReplace) + $this->requests($document),
            'properties' => $document->properties
                ->whereIn('Name', $names)
                ->sortByDesc('Id')
                ->unique('Name')
                ->sortBy(fn (DocumentAttribute $a) => $names->search($a->Name))
                ->map(fn (DocumentAttribute $a) => ['name' => $a->Name, 'value' => $a->Value])
                ->values()
                ->all(),
        ];
    }

    /**
     * What the external system shows next to a document. "state" is the single most
     * important reason for the file's current condition; the flags and dates let a screen show
     * more, including when a hold or review was released.
     *
     * @return array<string, mixed>
     *
     * @throws ErroredException
     */
    private function status(Document $document, DocumentService $service, ?string $lockReason, bool $allowReplace): array
    {
        $holdReleasedOn = $document->holds()
            ->where('t_DMSLegalHolds.Status', LegalHoldStatusEnum::Released->value)
            ->max('t_DMSLegalHolds.ReleasedOn');
        $reviewedOn = $document->reviews()->whereNotNull('ReviewedOn')->max('ReviewedOn');

        return [
            'state' => $lockReason ?? ($service->isValidated() ? 'validated' : 'active'),
            'onHold' => $service->isHold(),
            'underReview' => $service->isUnderReview(),
            'archived' => $service->isArchived(),
            'checkedOut' => $service->isCheckedOut() === 1,
            'pendingValidation' => $service->isPendingValidation(),
            'validated' => $service->isValidated(),
            'lockReason' => $lockReason,
            'canReplace' => $allowReplace && $lockReason === null,
            'holdReleasedOn' => $holdReleasedOn ? Carbon::parse($holdReleasedOn)->toIso8601String() : null,
            'lastReviewedOn' => $reviewedOn ? Carbon::parse($reviewedOn)->toIso8601String() : null,
        ];
    }

    /**
     * Ask for a review of the current version. The reviewer is the one the caller names, else
     * the event's default user or team; with neither the request cannot be routed. A team
     * review sits in every member's inbox and is completed by whichever member takes it.
     *
     * @throws ErroredException
     * @throws ValidationException
     */
    public function requestReview(SourceSystem $system, Document $document, string $actorId, ?string $actorName, ?string $note, ?string $reviewerUserId = null): DocumentReview
    {
        $this->assertNotLocked($document);

        $event = $document->eventType;
        $version = $document->current;
        if ($version === null) {
            throw new ErroredException('document has no content to review.');
        }

        $serviceUser = $system->serviceUser;
        [$reviewer, $team] = $this->resolveReviewer($event, $reviewerUserId, $serviceUser);

        $requestedBy = filled($actorName) ? "{$actorName} ({$actorId})" : $actorId;
        $requestNote = trim("Requested from {$system->Code} by {$requestedBy}." . (filled($note) ? " {$note}" : ''));

        $review = DB::transaction(function () use ($document, $version, $reviewer, $team, $serviceUser, $requestNote, $system, $actorId) {
            $review = DocumentReview::create([
                'DocumentId' => $document->Id,
                'VersionId' => $version->Id,
                'ReviewerId' => $reviewer?->Id,
                'ReviewerTeamId' => $team?->TeamID,
                'Status' => DocumentReviewStatusEnum::Pending->value,
                'RequestNote' => $requestNote,
                'CreatedBy' => $serviceUser->Id,
                'ModifiedBy' => $serviceUser->Id,
            ]);

            activity()->causedBy($serviceUser)->performedOn($document)->event('review-requested')
                ->log("Review requested from {$system->Code} by {$actorId} of {$document->Name} from " . ($reviewer?->Name ?? $team?->Name));

            return $review;
        });

        $this->notifyReviewers($review, $document, $reviewer, $team, $serviceUser, $requestNote);

        return $review;
    }

    /**
     * Put the current version through the event's validation type. The document stays where it
     * is; a validation is created against it and, when the type has a workflow, submitted to it.
     *
     * @throws ErroredException
     */
    public function requestValidation(SourceSystem $system, Document $document, string $actorId, ?string $actorName, ?string $note): DocumentValidation
    {
        $this->assertNotLocked($document);

        $type = $document->eventType?->validationType;
        if ($type === null) {
            throw new ErroredException('no validation type is configured for this kind of document.');
        }
        if ($document->current === null) {
            throw new ErroredException('document has no content to validate.');
        }

        $serviceUser = $system->serviceUser;

        $validation = DB::transaction(function () use ($system, $document, $type, $serviceUser, $actorId, $actorName, $note) {
            $validation = DocumentValidation::create([
                'Name' => $document->Name,
                'ValidationId' => $this->nextValidationId($type->ValidationTypeId, $document),
                'ValidationTypeId' => $type->Id,
                'DocumentId' => $document->Id,
                'Status' => DocumentValidationStatusEnum::Pending,
                'CreatedBy' => $serviceUser->Id,
                'ModifiedBy' => $serviceUser->Id,
            ]);

            $now = now();
            $context = [
                'source_system' => $system->Code,
                'entity_type' => $document->ExternalEntityType,
                'entity_id' => $document->ExternalEntityId,
                'requested_by' => $actorId,
                'requested_by_name' => $actorName,
                'note' => $note,
            ];
            $rows = collect($context)->filter(fn ($value) => filled($value))->map(fn ($value, $name) => [
                'Name' => $name, 'Value' => (string) $value, 'DataType' => 'st', 'DocumentValidationId' => $validation->Id,
                'CreatedBy' => $serviceUser->Id, 'ModifiedBy' => $serviceUser->Id, 'CreatedOn' => $now, 'ModifiedOn' => $now,
            ])->values()->all();
            DB::table('t_DocumentValidationAttributes')->insert($rows);

            activity()->causedBy($serviceUser)->performedOn($validation)->event('create')
                ->log("Validation requested from {$system->Code} by {$actorId} for {$document->Name}");

            return $validation;
        });

        // Same rule as the DMS upload screen: a type with a workflow starts it, except in
        // plain "validation" mode, where a validator picks the document up from the list.
        if ($type->hasWorkflow() && $validation->SubmissionMode !== DocumentValidationModeEnum::Validation) {
            app(DocumentValidationWorkflow::class)->submitValidation($validation->refresh(), $serviceUser, 'Submitted for validation from ' . $system->Code);
        }

        return $validation->refresh();
    }

    /**
     * @throws ErroredException
     */
    private function assertNotLocked(Document $document): void
    {
        $reason = (new DocumentService($document))->lockReason();
        if ($reason !== null) {
            throw new ErroredException('document cannot be changed: ' . str_replace('_', ' ', $reason) . '.');
        }
    }

    /**
     * @return array{0: ?User, 1: ?\App\Models\Auth\Team} exactly one is set
     *
     * @throws ErroredException
     * @throws ValidationException
     */
    private function resolveReviewer(?EventType $event, ?string $reviewerUserId, User $serviceUser): array
    {
        if (filled($reviewerUserId)) {
            $reviewer = User::query()->where('UserID', strtoupper(trim($reviewerUserId)))->first();
            if (! $reviewer instanceof User) {
                throw ValidationException::withMessages(['reviewer' => 'invalid reviewer selected.']);
            }
            if ($reviewer->Id === $serviceUser->Id) {
                throw ValidationException::withMessages(['reviewer' => 'the reviewer cannot be the requesting account.']);
            }

            return [$reviewer, null];
        }

        if ($event?->reviewerUser !== null) {
            return [$event->reviewerUser, null];
        }
        if ($event?->reviewerTeam !== null) {
            return [null, $event->reviewerTeam];
        }

        throw new ErroredException('no reviewer is configured for this kind of document; name a reviewer or set a default on the event.');
    }

    /**
     * Best effort: the review exists once created, so a mail failure must not undo it.
     */
    private function notifyReviewers(DocumentReview $review, Document $document, ?User $reviewer, ?\App\Models\Auth\Team $team, User $sender, string $requestNote): void
    {
        $recipients = $reviewer !== null ? collect([$reviewer]) : $team?->users()->get() ?? collect();

        foreach ($recipients as $recipient) {
            try {
                CRMEmailService::createUser(
                    user: $recipient,
                    subject: 'Document Review Requested: ' . $document->Name,
                    body: '<p>Hi ' . e($recipient->Name) . ', <br>A review of <strong>' . e($document->Name) . '</strong> has been requested.<br>' . e($requestNote) . '</p>',
                    actor: $sender
                );
            } catch (Throwable $e) {
                Log::warning('review request notification failed for ' . $recipient->UserID . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * @throws ErroredException
     */
    private function nextValidationId(string $typeCode, Document $document): string
    {
        $sequence = DocumentValidation::withTrashed()->where('DocumentId', $document->Id)->count() + 1;

        do {
            $id = "{$typeCode}-D{$document->Id}-{$sequence}";
            $sequence++;
        } while (DocumentValidation::withTrashed()->where('ValidationId', $id)->exists());

        return $id;
    }

    /**
     * The most recent review and validation of a document, for the external system to show.
     *
     * @return array<string, mixed>
     */
    private function requests(Document $document): array
    {
        $review = $document->reviews()->with(['reviewer', 'team'])->latest('Id')->first();
        $validation = $document->validations()->latest('Id')->first();

        return [
            'review' => $review === null ? null : [
                'status' => $review->Status->value === DocumentReviewStatusEnum::Reviewed->value ? 'reviewed' : 'pending',
                'reviewer' => $review->reviewerName() ?: null,
                'requestedOn' => $review->CreatedOn?->toIso8601String(),
                'reviewedOn' => $review->ReviewedOn?->toIso8601String(),
                'comment' => $review->ReviewComment,
            ],
            'validation' => $validation === null ? null : [
                'status' => strtolower($validation->Status->name),
                'requestedOn' => $validation->CreatedOn?->toIso8601String(),
                'decidedOn' => ($validation->ApprovedOn ?? $validation->RejectedOn)?->toIso8601String(),
                'rejectionReason' => $validation->RejectionReason,
            ],
        ];
    }

    /**
     * Who acted in the external system, kept on the document because the file itself is
     * owned by the service account.
     *
     * @return array<int, array{Name: string, Value: string, DataType: string}>
     */
    private function sourceAttributes(SourceSystem $system, string $eventCode, int $moduleId, string $actorId, ?string $actorName, bool $replaced = false): array
    {
        $rows = [
            ['Name' => $replaced ? 'source.replaced_by' : 'source.actor', 'Value' => $actorId, 'DataType' => 'st'],
        ];

        if (filled($actorName)) {
            $rows[] = ['Name' => $replaced ? 'source.replaced_by_name' : 'source.actor_name', 'Value' => (string) $actorName, 'DataType' => 'st'];
        }

        if (! $replaced) {
            $rows[] = ['Name' => 'source.system', 'Value' => $system->Code, 'DataType' => 'st'];
            $rows[] = ['Name' => 'source.event', 'Value' => $eventCode, 'DataType' => 'st'];
            $rows[] = ['Name' => 'source.module', 'Value' => (string) $moduleId, 'DataType' => 'st'];
        }

        return $rows;
    }

    /**
     * @param array<int, array{Name: string, Value: string, DataType: string}> $rows
     */
    private function storeAttributes(Document $document, User $actor, array $rows): void
    {
        $versionId = $document->current?->Id;

        foreach ($rows as $row) {
            DocumentAttribute::create($row + [
                'DocumentId' => $document->Id,
                'VersionId' => $versionId,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]);
        }
    }
}
