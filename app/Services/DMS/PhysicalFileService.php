<?php

namespace App\Services\DMS;

use App\Enums\Core\PermissionEnum;
use App\Enums\Core\RoleEnum;
use App\Enums\Core\VisibilityEnum;
use App\Exceptions\ErroredException;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\PhysicalDocument;
use App\Models\DMS\PhysicalDocumentRelation;
use App\Models\DMS\PhysicalFile;
use App\Models\DMS\PhysicalFileCheckout;
use App\Models\DMS\PhysicalFileMovement;
use App\Models\DMS\PhysicalFileRequest;
use App\Models\DMS\Registry;
use App\Models\DMS\StorageLocation;
use App\Traits\Services\SpecialPermissionsTrait;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Transaction boundary for the paper-file lifecycle.
 *
 * Current whereabouts are only changed here, together with a movement row, so
 * the fast current-state lookup and the chain of custody cannot drift apart.
 */
class PhysicalFileService
{
    use SpecialPermissionsTrait;

    private ?string $documentDescriptionColumn = null;

    public function __construct(private readonly StorageLocationService $locations)
    {
    }

    /**
     * @param  array<string,mixed>  $attributes
     * @param  array<int,array<string,mixed>>  $documents
     */
    public function register(
        array $attributes,
        StorageLocation $home,
        User $actor,
        array $documents = []
    ): PhysicalFile {
        return DB::transaction(function () use ($attributes, $home, $actor, $documents) {
            $file = new PhysicalFile($attributes);
            $file->HomeRegistryId = $home->RegistryId;
            $file->HomeLocationId = $home->Id;
            $file->CurrentRegistryId = $home->RegistryId;
            $file->CurrentLocationId = $home->Id;
            $file->CurrentHolderUserId = null;
            $file->FileStatusId = $this->locations->codeId('PhysicalFileStatusId', 'available');
            $file->Visibility = $attributes['Visibility'] ?? VisibilityEnum::Private->value;
            $file->CreatedBy = $actor->Id;
            $file->ModifiedBy = $actor->Id;
            $file->save();

            $file->permissions()->create([
                'Permission' => RoleEnum::Admin->value,
                'Party' => User::getPrimaryKey(),
                'PartyID' => $actor->Id,
                'CreatedBy' => $actor->Id,
                'ModifiedBy' => $actor->Id,
            ]);

            foreach ($documents as $index => $documentAttributes) {
                $this->createDocument($file, $documentAttributes, $index, $actor);
            }

            $this->recordMovement(
                $file,
                'registered',
                $actor,
                toRegistryId: (int) $home->RegistryId,
                toLocationId: (int) $home->Id,
                receivedBy: $actor,
                fromCurrent: false
            );

            return $file->refresh();
        });
    }

    /**
     * Files further paper documents into a file that already exists.
     *
     * Registration takes the documents that came in with the file; this is the
     * same act later on, when another paper joins a file already on the shelf.
     * New documents go after the ones already in the file so their order still
     * reflects the order they were filed in.
     *
     * Only a file the registry is holding can be filed into - see
     * assertHeldByRegistry().
     *
     * @param  array<int,array<string,mixed>>  $documents
     * @return Collection<int,PhysicalDocument>
     *
     * @throws Throwable
     */
    public function addDocuments(PhysicalFile $file, array $documents, User $actor): Collection
    {
        return DB::transaction(function () use ($file, $documents, $actor) {
            // Locked and re-read: a file being handed over at the same moment
            // must not take paper on its way out of the door.
            $file = PhysicalFile::query()->lockForUpdate()->findOrFail($file->Id);
            $this->assertHeldByRegistry($file);

            $lastOrder = PhysicalDocument::query()
                ->where('PhysicalFileId', $file->Id)
                ->whereNull('DeletedOn')
                ->max('SortOrder');

            $nextOrder = $lastOrder === null ? 0 : (int) $lastOrder + 1;

            $created = collect();

            foreach (array_values($documents) as $index => $documentAttributes) {
                $created->push(
                    $this->createDocument($file, $documentAttributes, $nextOrder + $index, $actor)
                );
            }

            $file->ModifiedBy = $actor->Id;
            $file->save();

            return $created;
        });
    }

    /**
     * @param  array<string,mixed>  $attributes
     *
     * @throws ErroredException
     */
    private function createDocument(
        PhysicalFile $file,
        array $attributes,
        int $sortOrder,
        User $actor
    ): PhysicalDocument {
        $document = PhysicalDocument::query()->create([
            'PhysicalFileId' => $file->Id,
            'Title' => trim((string) $attributes['Title']),
            $this->documentDescriptionColumn() => trim((string) $attributes['Description']),
            'DocumentDate' => $attributes['DocumentDate'] ?? null,
            'SortOrder' => $sortOrder,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);

        foreach ($attributes['Relations'] ?? [] as $relation) {
            $this->attachDocumentRelation(
                $document,
                (string) $relation['Related'],
                (string) $relation['RelatedID'],
                $actor
            );
        }

        return $document;
    }

    /**
     * Refuses a file the registry does not physically have.
     *
     * Filing is a physical act: the folder is opened and the paper goes in. A
     * file that is lent out, in transit or recorded as missing is not there to
     * be opened, so a document filed against it would record something that
     * never happened - and would go unnoticed until the file came back without
     * the paper the system says it holds.
     *
     * @throws ErroredException
     */
    public function assertHeldByRegistry(PhysicalFile $file): void
    {
        if ($file->CurrentHolderUserId !== null) {
            throw new ErroredException(
                'This file is out on loan. It can only be filed into once it is back in the registry.'
            );
        }

        if ($file->CurrentLocationId === null) {
            throw new ErroredException(
                'This file is in transit. It can only be filed into once the registry has received it.'
            );
        }

        if ((int) $file->FileStatusId === $this->locations->codeId('PhysicalFileStatusId', 'missing')) {
            throw new ErroredException('This file is recorded as missing, so nothing can be filed into it.');
        }
    }

    /**
     * Whether this file is on the shelf and can take more paper.
     *
     * The question the UI asks before offering to file into it; the answer is
     * enforced by assertHeldByRegistry() when the papers actually arrive.
     */
    public function isHeldByRegistry(PhysicalFile $file): bool
    {
        try {
            $this->assertHeldByRegistry($file);
        } catch (ErroredException) {
            return false;
        }

        return true;
    }

    /**
     * The column carrying a document's descriptive text differs between
     * databases of this age, so it is resolved once rather than assumed.
     */
    private function documentDescriptionColumn(): string
    {
        return $this->documentDescriptionColumn ??= (
            Schema::hasColumn('t_DMSPhysicalDocuments', 'Description') ? 'Description' : 'Notes'
        );
    }

    private function attachDocumentRelation(
        PhysicalDocument $document,
        string $related,
        string $relatedId,
        User $actor
    ): PhysicalDocumentRelation {
        $relatedClass = Relation::getMorphedModel($related);
        if ($relatedClass === null) {
            throw new ErroredException('Invalid physical document relation type.');
        }

        $relatedModel = new $relatedClass();
        if (! $relatedClass::query()->where($relatedModel->getKeyName(), $relatedId)->exists()) {
            throw new ErroredException('The related record no longer exists.');
        }

        return PhysicalDocumentRelation::query()->create([
            'PhysicalDocumentId' => $document->Id,
            'Related' => $related,
            'RelatedID' => $relatedId,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);
    }

    /**
     * A discoverable file may be requested. This deliberately checks the file
     * permission, not merely access to the cabinet containing it.
     */
    public function request(
        PhysicalFile $file,
        User $requester,
        CarbonInterface $neededFrom,
        CarbonInterface $requestedUntil,
        string $purpose
    ): PhysicalFileRequest {
        if (! $this->canSee($file, $requester)) {
            throw new ErroredException('You do not have permission to request this physical file.');
        }

        if ($requestedUntil->lessThan($neededFrom)) {
            throw new ErroredException('The requested return date must be after the needed-from date.');
        }

        if (trim($purpose) === '') {
            throw new ErroredException('State why the physical file is required.');
        }

        return DB::transaction(function () use ($file, $requester, $neededFrom, $requestedUntil, $purpose) {
            $statusIds = [
                $this->locations->codeId('PhysicalFileRequestStatusId', 'pending'),
                $this->locations->codeId('PhysicalFileRequestStatusId', 'approved'),
            ];

            $overlap = PhysicalFileRequest::query()
                ->where('PhysicalFileId', $file->Id)
                ->whereIn('RequestStatusId', $statusIds)
                ->where('NeededFrom', '<=', $requestedUntil)
                ->where('RequestedUntil', '>=', $neededFrom)
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw new ErroredException('This file already has a pending or approved request for those dates.');
            }

            return PhysicalFileRequest::query()->create([
                'PhysicalFileId' => $file->Id,
                'RequestedByUserId' => $requester->Id,
                'RequestedOn' => now(),
                'NeededFrom' => $neededFrom,
                'RequestedUntil' => $requestedUntil,
                'Purpose' => trim($purpose),
                'RequestStatusId' => $statusIds[0],
                'CreatedBy' => $requester->Id,
                'ModifiedBy' => $requester->Id,
            ]);
        });
    }

    /**
     * Answers a request on behalf of the registry.
     *
     * Nobody answers their own ask, however senior: the whole point of asking
     * is that somebody else weighs whether the file should leave the registry.
     * A HOD who needs a file waits for another approver, or an administrator.
     */
    public function decide(
        PhysicalFileRequest $request,
        User $actor,
        bool $approve,
        ?string $remark = null
    ): PhysicalFileRequest {
        return DB::transaction(function () use ($request, $actor, $approve, $remark) {
            $request = PhysicalFileRequest::query()->lockForUpdate()->findOrFail($request->Id);
            $file = $request->physicalFile()->with('homeRegistry')->firstOrFail();
            $this->assertRegistryApprover($file->homeRegistry, $actor);

            if ((int) $request->RequestedByUserId === (int) $actor->Id) {
                throw new ErroredException('You cannot decide a physical-file request you raised yourself.');
            }

            $pending = $this->locations->codeId('PhysicalFileRequestStatusId', 'pending');
            if ((int) $request->RequestStatusId !== $pending) {
                throw new ErroredException('Only a pending physical-file request can be decided.');
            }

            $request->RequestStatusId = $this->locations->codeId(
                'PhysicalFileRequestStatusId',
                $approve ? 'approved' : 'rejected'
            );
            $request->DecidedByUserId = $actor->Id;
            $request->DecidedOn = now();
            $request->DecisionRemark = $remark;
            $request->ModifiedBy = $actor->Id;
            $request->save();

            return $request->refresh();
        });
    }

    /**
     * Takes back an approval before the file has left the registry.
     *
     * Only an approval can be withdrawn this way: once the file is in the
     * requester's hands the way back is a return, not a cancellation, and a
     * request still pending is answered rather than cancelled.
     *
     * @throws Throwable
     */
    public function cancel(
        PhysicalFileRequest $request,
        User $actor,
        ?string $reason = null
    ): PhysicalFileRequest {
        return DB::transaction(function () use ($request, $actor, $reason) {
            $request = PhysicalFileRequest::query()->lockForUpdate()->findOrFail($request->Id);
            $file = $request->physicalFile()->firstOrFail();
            $registry = $file->homeRegistry()->firstOrFail();

            if (! $this->canCancel($request, $registry, $actor)) {
                throw new ErroredException(
                    'Only the approver or a registry administrator may cancel an approved request.'
                );
            }

            $approved = $this->locations->codeId('PhysicalFileRequestStatusId', 'approved');
            if ((int) $request->RequestStatusId !== $approved) {
                throw new ErroredException(
                    'Only an approved request that has not been handed over can be cancelled.'
                );
            }

            $request->RequestStatusId = $this->locations->codeId('PhysicalFileRequestStatusId', 'cancelled');
            $request->CancelledByUserId = $actor->Id;
            $request->CancelledOn = now();
            $request->CancellationReason = $reason;
            $request->ModifiedBy = $actor->Id;
            $request->save();

            return $request->refresh();
        });
    }

    /**
     * Hands the file over to the requester and moves custody with it.
     *
     * This is the moment the approval becomes a loan: the file leaves the
     * shelf, the requester becomes its holder, and the request is spent.
     *
     * @throws Throwable
     */
    public function checkout(
        PhysicalFileRequest $request,
        User $issuer,
        ?string $remark = null
    ): PhysicalFileCheckout {
        return DB::transaction(function () use ($request, $issuer, $remark) {
            $request = PhysicalFileRequest::query()->lockForUpdate()->findOrFail($request->Id);
            $file = PhysicalFile::query()->with('homeRegistry')->lockForUpdate()
                ->findOrFail($request->PhysicalFileId);

            $this->assertRegistryApprover($file->homeRegistry, $issuer);

            $approved = $this->locations->codeId('PhysicalFileRequestStatusId', 'approved');
            if ((int) $request->RequestStatusId !== $approved) {
                throw new ErroredException('The request must be approved before checkout.');
            }

            // The loan is due back on the day that was asked for, so a request
            // whose period has run out cannot be handed over at all.
            if ($request->RequestedUntil !== null && $request->RequestedUntil->isPast()) {
                throw new ErroredException(
                    'The period this file was approved for has passed; a fresh request is needed.'
                );
            }

            if ($file->activeCheckout()->exists()) {
                throw new ErroredException('This physical file is already checked out.');
            }

            if ($file->CurrentLocationId === null || $file->CurrentHolderUserId !== null) {
                throw new ErroredException('The registry must physically hold the file before checkout.');
            }

            $checkout = PhysicalFileCheckout::query()->create([
                'PhysicalFileId' => $file->Id,
                'RequestId' => $request->Id,
                'BorrowerUserId' => $request->RequestedByUserId,
                'IssuedByUserId' => $issuer->Id,
                'CheckedOutOn' => now(),
                'DueOn' => $request->RequestedUntil,
                'CheckOutRemark' => $remark,
                'CheckoutStatusId' => $this->locations->codeId(
                    'PhysicalFileCheckoutStatusId',
                    'active'
                ),
                'CreatedBy' => $issuer->Id,
                'ModifiedBy' => $issuer->Id,
            ]);

            $borrower = User::withTrashed()->findOrFail($request->RequestedByUserId);

            $this->recordMovement(
                $file,
                'checked_out',
                $issuer,
                request: $request,
                checkout: $checkout,
                toHolderUserId: (int) $request->RequestedByUserId,
                receivedBy: $borrower
            );

            $file->CurrentRegistryId = null;
            $file->CurrentLocationId = null;
            $file->CurrentHolderUserId = $request->RequestedByUserId;
            $file->FileStatusId = $this->locations->codeId('PhysicalFileStatusId', 'checked_out');
            $file->ModifiedBy = $issuer->Id;
            $file->save();

            $request->RequestStatusId = $this->locations->codeId(
                'PhysicalFileRequestStatusId',
                'fulfilled'
            );
            $request->ModifiedBy = $issuer->Id;
            $request->save();

            return $checkout->refresh();
        });
    }

    /**
     * Closes a loan: the file comes back and lands on a shelf again.
     *
     * The counterpart of checkout(). Custody returns to the registry in the
     * same breath as the shelving, so a file is never recorded as returned
     * while nobody can say where it is.
     *
     * @throws Throwable
     */
    public function checkin(
        PhysicalFileCheckout $checkout,
        StorageLocation $returnedTo,
        User $receiver,
        ?string $remark = null
    ): PhysicalFileCheckout {
        return DB::transaction(function () use ($checkout, $returnedTo, $receiver, $remark) {
            $checkout = PhysicalFileCheckout::query()->lockForUpdate()->findOrFail($checkout->Id);
            $file = PhysicalFile::query()->with('homeRegistry')->lockForUpdate()
                ->findOrFail($checkout->PhysicalFileId);

            $this->assertRegistryApprover($file->homeRegistry, $receiver);
            if ($checkout->ClosedOn !== null) {
                throw new ErroredException('This physical-file checkout is already closed.');
            }

            // A loan ends where it began. Moving the file to another registry
            // is a transfer, which is dispatched and received rather than
            // slipped in through the return counter.
            if ((int) $returnedTo->RegistryId !== (int) $file->HomeRegistryId) {
                throw new ErroredException('A returned file goes back to the registry that keeps it.');
            }

            $now = now();
            $checkout->CheckoutStatusId = $this->locations->codeId(
                'PhysicalFileCheckoutStatusId',
                'returned'
            );
            $checkout->ClosedOn = $now;
            $checkout->ReturnedOn = $now;
            $checkout->ReturnedToLocationId = $returnedTo->Id;
            $checkout->ReceivedByUserId = $receiver->Id;
            $checkout->CheckInRemark = $remark;
            $checkout->ModifiedBy = $receiver->Id;
            $checkout->save();

            $this->recordMovement(
                $file,
                'returned',
                $receiver,
                checkout: $checkout,
                toRegistryId: (int) $returnedTo->RegistryId,
                toLocationId: (int) $returnedTo->Id,
                receivedBy: $receiver
            );

            $file->CurrentRegistryId = $returnedTo->RegistryId;
            $file->CurrentLocationId = $returnedTo->Id;
            $file->CurrentHolderUserId = null;
            $file->FileStatusId = $this->locations->codeId(
                'PhysicalFileStatusId',
                $this->locationStatusValue($file, $returnedTo)
            );
            $file->ModifiedBy = $receiver->Id;
            $file->save();

            return $checkout->refresh();
        });
    }

    /**
     * Starts a hand-off. While it is open the file has no current custodian;
     * the destination is retained on the movement awaiting receipt.
     *
     * @throws Throwable
     */
    public function dispatch(
        PhysicalFile $file,
        User $actor,
        ?StorageLocation $toLocation = null,
        ?User $toHolder = null,
        ?string $reason = null
    ): PhysicalFileMovement {
        if (($toLocation === null) === ($toHolder === null)) {
            throw new ErroredException('Choose either one destination location or one receiving person.');
        }

        return DB::transaction(function () use ($file, $actor, $toLocation, $toHolder, $reason) {
            $file = PhysicalFile::query()->lockForUpdate()->findOrFail($file->Id);
            $this->assertRegistryApprover($file->homeRegistry()->firstOrFail(), $actor);

            $openMovement = $file->movements()->whereNull('ReceivedOn')->lockForUpdate()->exists();
            if ($openMovement
                || ($file->CurrentLocationId === null && $file->CurrentHolderUserId === null)) {
                throw new ErroredException('This physical file is already in transit.');
            }

            $movement = $this->recordMovement(
                $file,
                'dispatched',
                $actor,
                toRegistryId: $toLocation ? (int) $toLocation->RegistryId : null,
                toLocationId: $toLocation ? (int) $toLocation->Id : null,
                toHolderUserId: $toHolder ? (int) $toHolder->Id : null
            );
            $movement->Reason = $reason;
            $movement->save();

            $file->CurrentRegistryId = null;
            $file->CurrentLocationId = null;
            $file->CurrentHolderUserId = null;
            $file->FileStatusId = $this->locations->codeId('PhysicalFileStatusId', 'in_transit');
            $file->ModifiedBy = $actor->Id;
            $file->save();

            return $movement->refresh();
        });
    }

    /** @throws Throwable */
    public function receive(PhysicalFileMovement $movement, User $receiver): PhysicalFile
    {
        return DB::transaction(function () use ($movement, $receiver) {
            $movement = PhysicalFileMovement::query()->lockForUpdate()->findOrFail($movement->Id);
            $file = PhysicalFile::query()->lockForUpdate()->findOrFail($movement->PhysicalFileId);

            if ($movement->ReceivedOn !== null) {
                throw new ErroredException('This movement has already been received.');
            }

            if ($movement->ToLocationId === null && $movement->ToHolderUserId === null) {
                throw new ErroredException('This movement has no receivable destination.');
            }

            if ($movement->ToHolderUserId !== null
                && (int) $movement->ToHolderUserId !== (int) $receiver->Id) {
                throw new ErroredException('Only the named recipient may receive this file.');
            }

            $destination = null;
            if ($movement->ToLocationId !== null) {
                $destination = StorageLocation::query()->findOrFail($movement->ToLocationId);
                $this->assertRegistryApprover($destination->registry()->firstOrFail(), $receiver);
            }

            $movement->ReceivedByUserId = $receiver->Id;
            $movement->ReceivedOn = now();
            $movement->ModifiedBy = $receiver->Id;
            $movement->save();

            $file->CurrentRegistryId = $movement->ToRegistryId;
            $file->CurrentLocationId = $movement->ToLocationId;
            $file->CurrentHolderUserId = $movement->ToHolderUserId;

            if ($movement->ToHolderUserId !== null) {
                $file->FileStatusId = $this->locations->codeId('PhysicalFileStatusId', 'with_person');
            } else {
                $file->FileStatusId = $this->locations->codeId(
                    'PhysicalFileStatusId',
                    $this->locationStatusValue($file, $destination)
                );
            }

            $file->ModifiedBy = $receiver->Id;
            $file->save();

            return $file->refresh();
        });
    }

    /** @throws Throwable */
    public function archive(PhysicalFile $file, StorageLocation $archiveLocation, User $actor): PhysicalFile
    {
        $archiveRegistry = Registry::query()->findOrFail($archiveLocation->RegistryId);
        if (! $archiveRegistry->IsArchive) {
            throw new ErroredException('Select a location in a registry marked as an archive.');
        }

        return DB::transaction(function () use ($file, $archiveLocation, $actor) {
            $file = PhysicalFile::query()->lockForUpdate()->findOrFail($file->Id);
            $this->assertRegistryApprover($file->homeRegistry()->firstOrFail(), $actor);

            if ($file->activeCheckout()->exists()) {
                throw new ErroredException('A checked-out file cannot be archived.');
            }

            if ($file->CurrentLocationId === null || $file->CurrentHolderUserId !== null) {
                throw new ErroredException('A file in transit or held by a person cannot be archived.');
            }

            $this->recordMovement(
                $file,
                'archived',
                $actor,
                toRegistryId: (int) $archiveLocation->RegistryId,
                toLocationId: (int) $archiveLocation->Id,
                receivedBy: $actor
            );

            $file->CurrentRegistryId = $archiveLocation->RegistryId;
            $file->CurrentLocationId = $archiveLocation->Id;
            $file->CurrentHolderUserId = null;
            $file->FileStatusId = $this->locations->codeId('PhysicalFileStatusId', 'archived');
            $file->ArchivedOn = now();
            $file->ModifiedBy = $actor->Id;
            $file->save();

            return $file->refresh();
        });
    }

    public function canSee(PhysicalFile $file, User $user): bool
    {
        return PhysicalFile::query()->user($user)->whereKey($file->Id)->exists();
    }

    /**
     * Sharing a paper file works the same way as sharing a document: the right
     * comes from an Admin or Share grant on the file itself, not from the
     * registry, so whoever registered it can pass it on.
     */
    public function canShare(PhysicalFile $file, User $actor): bool
    {
        return $this->_checkPermissions($file, $actor);
    }

    /**
     * @throws ErroredException
     */
    public function share(
        PhysicalFile $file,
        User|Team $assignee,
        RoleEnum $role,
        User $actor,
        bool $notify = true
    ): SpecialPermission {
        return $this->_addPermissions($file, $assignee, $role, $actor, $notify);
    }

    /**
     * @throws ErroredException
     */
    public function unshare(PhysicalFile $file, SpecialPermission $permission, User $actor): PhysicalFile
    {
        return $this->_trashPermissions($file, $permission, $actor);
    }

    public function canApprove(Registry $registry, User $actor): bool
    {
        if ((int) $registry->HodUserId === (int) $actor->Id
            || $actor->can(PermissionEnum::Admin->value)) {
            return true;
        }

        // userGrant() rather than userRole(): a registry is public so it can be
        // found, which must not amount to authority to approve its requests.
        return Registry::query()
            ->userGrant($actor, [RoleEnum::Admin->value])
            ->whereKey($registry->Id)
            ->exists();
    }

    /**
     * Who may take an approval back.
     *
     * The approver, because it was their word; and anyone with authority over
     * the registry, because the file is still theirs to withhold. Whether the
     * request is in a state that can be cancelled is checked in cancel().
     */
    public function canCancel(PhysicalFileRequest $request, Registry $registry, User $actor): bool
    {
        return (int) $request->DecidedByUserId === (int) $actor->Id
            || $this->canApprove($registry, $actor);
    }

    private function assertRegistryApprover(Registry $registry, User $actor): void
    {
        if (! $this->canApprove($registry, $actor)) {
            throw new ErroredException('Only the registry HOD or an authorised delegate may do that.');
        }
    }

    private function recordMovement(
        PhysicalFile $file,
        string $type,
        User $actor,
        ?PhysicalFileRequest $request = null,
        ?PhysicalFileCheckout $checkout = null,
        ?int $toRegistryId = null,
        ?int $toLocationId = null,
        ?int $toHolderUserId = null,
        ?User $receivedBy = null,
        bool $fromCurrent = true
    ): PhysicalFileMovement {
        return PhysicalFileMovement::query()->create([
            'PhysicalFileId' => $file->Id,
            'RequestId' => $request?->Id,
            'CheckoutId' => $checkout?->Id,
            'FromRegistryId' => $fromCurrent ? $file->CurrentRegistryId : null,
            'FromLocationId' => $fromCurrent ? $file->CurrentLocationId : null,
            'FromHolderUserId' => $fromCurrent ? $file->CurrentHolderUserId : null,
            'ToRegistryId' => $toRegistryId,
            'ToLocationId' => $toLocationId,
            'ToHolderUserId' => $toHolderUserId,
            'MovementTypeId' => $this->locations->codeId('PhysicalFileMovementTypeId', $type),
            'DispatchedByUserId' => $actor->Id,
            'DispatchedOn' => now(),
            'ReceivedByUserId' => $receivedBy?->Id,
            'ReceivedOn' => $receivedBy ? now() : null,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);
    }

    private function locationStatusValue(PhysicalFile $file, StorageLocation $location): string
    {
        if ((int) $location->Id === (int) $file->HomeLocationId) {
            return 'available';
        }

        $location->loadMissing(['type', 'purpose']);
        $isTray = $location->type?->Value === 'tray'
            || in_array($location->purpose?->Value, ['receiving', 'returns'], true);

        return $isTray ? 'in_tray' : 'available';
    }
}
