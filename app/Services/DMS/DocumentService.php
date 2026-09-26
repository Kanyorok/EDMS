<?php

namespace App\Services\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Enums\Core\ExtensionsEnum;
use App\Enums\Core\ModulesEnum;
use App\Enums\Core\RoleEnum;
use App\Enums\Core\VisibilityEnum;
use App\Enums\DMS\DisksEnum;
use App\Enums\DMS\DocumentCheckOutStatusEnum;
use App\Enums\DMS\DocumentReviewStatusEnum;
use App\Enums\DMS\LegalHoldStatusEnum;
use App\Events\DMS\DocumentUploadedEvent;
use App\Exceptions\ErroredException;
use App\Helpers\SystemHelper;
use App\Models\Auth\Team;
use App\Models\Auth\User;
use App\Models\Core\Approval\CodeDetail;
use App\Models\Core\CategoryMaster;
use App\Models\Core\SpecialPermission;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\DMSSignature;
use App\Models\DMS\Document;
use App\Models\DMS\DocumentCheckOut;
use App\Models\DMS\DocumentRelation;
use App\Models\DMS\DocumentReview;
use App\Models\DMS\DocumentVersion;
use App\Models\DMS\Repository;
use App\Services\Core\PermissionsService;
use App\Services\DMS\Files\FileProperties;
use App\Services\DMS\Verification\SignatureService;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

class DocumentService extends PermissionsService
{
    protected const string CHECKSUM = 'sha256'; // Consistency! @Mureithi
    public ExtensionsEnum $type;

    public function __construct(public Document $document)
    {
        $this->setType();
    }

    private function setType(): void
    {
        try {
            $type = ExtensionsEnum::fromMimeType($this->document->MimeType);
        } catch (ErroredException $e) {
            $type = ExtensionsEnum::None;
        }
        $this->type = $type;
    }

    /**
     * @throws ErroredException
     */
    public static function createInternal(ModulesEnum $module, UploadedFile $file, User $actor, array|string $permissions, string $Related, string|int $RelatedId, CodeDetail $type = null): self
    {
        $service = self::createUpload(RepositoryService::module($module), $file, $actor, false)
            ->addPermission($actor, RoleEnum::Admin, SystemHelper::user(), false)->attach($Related, $RelatedId, $actor, $type);

        if (is_string($permissions)) {
            $permissions = explode(',', $permissions);
        }
        self::userPermissions($service->document, $permissions, RoleEnum::Read, $actor);

        return $service;
    }

    /**
     * @throws ErroredException
     */
    public function sign(DMSSignature $signature, User $actor, int $Pages = 1): static
    {
        if (! $this->type->canSign()) {
            throw new ErroredException('Document cannot be signed ');
        }
        (new SignatureService($signature))->sign($this->document, $actor, $Pages);

        return $this;
    }

    /**
     * @throws ErroredException
     */
    public static function createInternalFileContent(ModulesEnum $module, ExtensionsEnum $extension, string $fileName, string $content, User $actor, array|string $permissions, string $Related, string|int $RelatedId, CodeDetail $type = null): self
    {
        $service = self::createContent(RepositoryService::module($module), $extension, $fileName, $content, $actor, false)
            ->addPermission($actor, RoleEnum::Admin, SystemHelper::user(), false)->attach($Related, $RelatedId, $actor, $type);

        if (empty($permissions)) {
            if (is_string($permissions)) {
                $permissions = explode(',', $permissions);
            }
            self::userPermissions($service->document, $permissions, RoleEnum::Read, $actor);
        }

        return $service;
    }

    /**
     * @throws ErroredException
     */
    public function attach(string $Related, string|int $RelatedId, User $actor, CodeDetail $type = null): self
    {
        if (is_null(Relation::getMorphedModel($Related))) {
            throw new ErroredException('Invalid Related Entity');
        }

        if ($type instanceof CodeDetail && $type->CodeID !== 'DocumentTypeId' && $type->CodeID !== 'Document Types') {
            throw new ErroredException("Invalid document type provided");
        }

        DocumentRelation::create([
            'Related' => $Related,
            'RelatedID' => $RelatedId,
            'DocumentTypeId' => $type?->ID,
            'DocumentId' => $this->document->Id,
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);

        activity()->causedBy($actor)->performedOn($this->document)->event('upload')->log('Document relation to ' . $Related);

        return $this;
    }

    /**
     * @throws ErroredException
     */
    public function addPermission(User|Team $assignee, RoleEnum $role, User $actor, bool $notify = true): static
    {
        $this->_addPermissions($this->document, $assignee, $role, $actor, $notify);

        return $this;
    }

    public static function getDisk(): DisksEnum
    {
        return DisksEnum::Local;
    }

    /**
     * @throws ErroredException
     */
    public static function createContent(Repository $repository, ExtensionsEnum $extension, string $fileName, string $content, User $actor, bool $copyRepoPermissions = true): self
    {
        $disk = self::getDisk();
        $checksum1 = hash(self::CHECKSUM, $content);
        $path = self::_saveFile($disk, $content);

        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));
        $checksum = base64_encode($checksum1 . '|' . $checksum2);

        if (! str_ends_with(strtolower($fileName), '.' . strtolower($extension->value))) {
            $fileName .= '.' . $extension->value;
        }

        return self::_create($repository, $actor, $disk, $fileName, $extension, $path, Storage::disk($disk->value)->size($path), $checksum, copyPermissions: $copyRepoPermissions);
    }

    /**
     * @throws ErroredException
     */
    /**
     * @param array{DocumentTypeId?: ?int, LinkedToTypeId?: ?int, Related?: ?string, LinkedId?: ?string, FolioId?: ?int, Notes?: ?string, ExpiryDate?: ?string} $uploadRules
     * @throws ErroredException
     */
    public static function createUpload(Repository $repository, UploadedFile $file, User $actor, bool $copyRepoPermissions = true, array $uploadRules = []): self
    {
        $extension = ExtensionsEnum::fromMimeType($file->getMimeType() ?? $file->getClientMimeType());
        $disk = self::getDisk();

        $checksum1 = hash_file(self::CHECKSUM, $file->getRealPath());
        $path = self::_saveFile($disk, $file->getContent());
        $properties = (new UploadFileProperties($file, $extension))->getProperties();
        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));
        $checksum = base64_encode($checksum1 . '|' . $checksum2);

        return self::_create($repository, $actor, $disk, $file->getClientOriginalName(), $extension, $path, $file->getSize(), $checksum, copyPermissions: $copyRepoPermissions, properties: $properties, uploadRules: $uploadRules);
    }

    /**
     * @throws ErroredException
     */
    private static function _saveFile(DisksEnum $disk, string $contents): string
    {
        $path = $disk->path() . '/' . Uuid::uuid4()->toString() . '.data';
        if (Storage::disk($disk->value)->put($path, (new EncryptionService())->encrypt($contents))) {
            return $path;
        }

        throw new ErroredException('Saving file failed.');
    }

    /**
     * @throws ErroredException
     */
    private static function _create(Repository $repository, User $actor, DisksEnum $disk, string $name, ExtensionsEnum $extension, string $path, int $sizeInBytes, string $checksum, CategoryMaster $category = null, bool $copyPermissions = true, Collection $properties = null, array $uploadRules = []): DocumentService
    {
        try {
            return DB::transaction(static function () use ($path, $checksum, $properties, $sizeInBytes, $disk, $category, $extension, $repository, $name, $actor, $copyPermissions, $uploadRules) {
                $documentType = ! empty($uploadRules['DocumentTypeId'])
                    ? DMSDocumentType::query()->with(['tag', 'repository'])->find($uploadRules['DocumentTypeId'])
                    : null;

                // The client already routes the upload to the document type's own
                // repository - this just refuses to file it anywhere else if that
                // routing was bypassed or tampered with.
                if ($documentType?->RepositoryId && (int) $documentType->RepositoryId !== (int) $repository->Id) {
                    throw new ErroredException(
                        "Document type {$documentType->Code} files into "
                        . ($documentType->repository->Name ?? 'a different repository') . ', not ' . $repository->Name . '.'
                    );
                }

                $document = Document::create([
                    "Name" => $name,
                    "MimeType" => $extension->getMimeType(),
                    "CategoryId" => $category?->Id,
                    'DocumentId' => Uuid::uuid4()->toString(),
                    "RepositoryId" => $repository->Id,
                    "Visibility" => $repository->Visibility->value,
                    "DocumentTypeId" => $uploadRules['DocumentTypeId'] ?? null,
                    "LinkedToTypeId" => $uploadRules['LinkedToTypeId'] ?? null,
                    "Related" => $uploadRules['Related'] ?? null,
                    "LinkedId" => $uploadRules['LinkedId'] ?? null,
                    "FolioId" => $uploadRules['FolioId'] ?? null,
                    "Notes" => $uploadRules['Notes'] ?? null,
                    "ExpiryDate" => $uploadRules['ExpiryDate'] ?? null,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                ]);

                if ($copyPermissions && $repository->Visibility->value === VisibilityEnum::Private->value) {
                    self::copyPermissions($repository, $document, $actor);
                }

                activity()->causedBy($actor)->performedOn($document)->event('upload')->log('Uploaded ' . explode($extension->getMimeType(), '/')[0] . ' to folder ' . $repository->Name);

                if ($documentType?->tag) {
                    (new TagService($documentType->tag))->attach($document, $actor);
                }

                // Copied onto the document as metadata (t_DocumentAttributes) rather
                // than left as type-level config, so it can filter individual files
                // later the same way any other document attribute does.
                if ($documentType && filled($documentType->EntityApplicability)) {
                    $properties = ($properties ?? collect())->push([
                        'Name' => 'EntityApplicability',
                        'Value' => $documentType->EntityApplicability,
                        'DataType' => DataTypesEnum::String->value,
                    ]);
                }

                $service = new self($document);
                if ($repository->Visibility->value === VisibilityEnum::Public->value) {
                    $service->addPermission($actor, RoleEnum::Admin, SystemHelper::user(), false);
                }

                return $service->_newVersion($disk, $path, $name, $sizeInBytes, $actor, $properties, $checksum, description: 'Initial upload');
            });
        } catch (ErroredException $e) {
            throw $e;
        } catch (Exception | Throwable $e) {
            Log::error('Error creating document: ');
            Log::error($e);

            throw new ErroredException('Saving file failed.');
        }
    }

    /**
     * @throws ErroredException
     */
    protected function _newVersion(DisksEnum $disk, string $path, string $name, int $sizeInBytes, User $actor, ?Collection $properties = null, ?string $checksum = null, ExtensionsEnum $extension = null, ?string $description = null): static
    {

        $this->document->versions()->create([
            "Name" => $name,
            "Version" => $this->document->versions()->count() + 1,
            "Path" => $path,
            "Disk" => $disk->value,
            "Checksum" => $checksum ?? (new FileProperties($this->document))->generateChecksum($disk, $path),
            "Size" => $sizeInBytes,
            "Description" => $description !== '' ? $description : null,
            "Blob" => '', // Uses for search params
            'CreatedBy' => $actor->Id,
            'ModifiedBy' => $actor->Id,
        ]);

        $this->document->update([
            'Name' => $name,
            'MimeType' => $extension ? $extension->getMimeType() : $this->document->MimeType,
        ]);

        $generate = true;

        if ($properties instanceof Collection) {
            (new FileProperties($this->document))->setProperties($actor, $properties);
            $generate = false;
        }

        event(new DocumentUploadedEvent($this->document, $generate));

        return $this;
    }

    /**
     * @throws ErroredException
     */
    public function checkout(User $actor, string $remark = ''): static
    {
        if ($this->isHold()) {
            throw new ErroredException('document is on legal hold !');
        }

        if ($this->isUnderReview()) {
            throw new ErroredException('document is under review !');
        }

        if ($this->isArchived()) {
            throw new ErroredException('document is archived !');
        }

        if ($this->isCheckedOut()) {
            throw new ErroredException('document is already checked out !');
        }

        try {
            return DB::transaction(function () use ($actor, $remark) {
                $date = now();
                DocumentCheckOut::create([
                    'DocumentId' => $this->document->Id,
                    'Status' => DocumentCheckOutStatusEnum::CheckOut->value,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                    'CheckOutRemark' => $remark,
                    'Dated' => $date,
                ]);
                activity()->causedBy($actor)->performedOn($this->document)->event('checked-out')->log('document checked out');

                return $this;
            });
        } catch (Throwable $e) {
            Log::error('Error checking out document: ' . $e);
        }

        throw new ErroredException('checking out document failed');
    }

    /**
     * Bypasses the normal checkout guards (hold/under-review/archived/already-checked-out) and
     * cancels any active checkout first. Used when the system itself needs to hand the document
     * back out - e.g. once a review completes, so the requester can check in the corrected file
     * without racing whoever might already hold it.
     *
     * @throws ErroredException
     */
    public function forceCheckout(User $actor, string $remark = ''): static
    {
        try {
            return DB::transaction(function () use ($actor, $remark) {
                $this->document->checkouts()
                    ->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)
                    ->update([
                        'Status' => DocumentCheckOutStatusEnum::Canceled->value,
                        'Dated' => now(),
                        'CheckInRemark' => 'canceled for a force checkout',
                        'ModifiedBy' => $actor->Id,
                    ]);

                DocumentCheckOut::create([
                    'DocumentId' => $this->document->Id,
                    'Status' => DocumentCheckOutStatusEnum::CheckOut->value,
                    'CreatedBy' => $actor->Id,
                    'ModifiedBy' => $actor->Id,
                    'CheckOutRemark' => $remark,
                    'Dated' => now(),
                ]);
                activity()->causedBy($actor)->performedOn($this->document)->event('checked-out')->log('document force checked out');

                return $this;
            });
        } catch (Throwable $e) {
            Log::error('Error force checking out document: ' . $e);
        }

        throw new ErroredException('force checking out document failed');
    }

    public function isHold(): bool
    {
        return $this->document->holds()->where('Status', LegalHoldStatusEnum::Active->value)->exists();
    }

    public function isArchived(): bool
    {
        return filled($this->document->ArchivedOn);
    }

    /**
     * @throws ErroredException
     */
    public function archive(User $actor, string $reason = 'manual'): static
    {
        if ($this->isArchived()) {
            throw new ErroredException('document is already archived !');
        }

        if ($this->isUnderReview()) {
            throw new ErroredException('document is under review and cannot be archived !');
        }

        try {
            return DB::transaction(function () use ($actor, $reason) {
                $this->document->forceFill([
                    'ArchivedOn' => now(),
                    'ArchivedBy' => $actor->Id,
                    'ArchiveReason' => $reason,
                ])->save();

                activity()->causedBy($actor)->performedOn($this->document)->event('archive')->log('archived document ' . $this->document->Name . ' (' . $reason . ')');

                return $this;
            });
        } catch (Throwable $e) {
            Log::error('Error archiving document: ' . $e);
        }

        throw new ErroredException('archiving document failed');
    }

    /**
     * @throws ErroredException
     */
    public function unarchive(User $actor): static
    {
        if (! $this->isArchived()) {
            throw new ErroredException('document is not archived !');
        }

        try {
            return DB::transaction(function () use ($actor) {
                $this->document->forceFill([
                    'ArchivedOn' => null,
                    'ArchivedBy' => null,
                    'ArchiveReason' => null,
                ])->save();

                activity()->causedBy($actor)->performedOn($this->document)->event('unarchive')->log('unarchived document ' . $this->document->Name);

                return $this;
            });
        } catch (Throwable $e) {
            Log::error('Error unarchiving document: ' . $e);
        }

        throw new ErroredException('unarchiving document failed');
    }

    public function isPendingValidation(): bool
    {
        // A rejected validation also has a null ApprovedBy - reject() deliberately never sets
        // it - so it must be excluded here too, or a rejected-and-checked-out-for-correction
        // document reads as still "pending validation" forever and the check-in action never
        // becomes reachable.
        return $this->document->validations()->whereNull('ApprovedBy')->whereNull('RejectedBy')->exists();
    }

    public function isValidated(): bool
    {
        return $this->document->validations()->whereNotNull('ApprovedBy')->exists();
    }

    public function pendingReview(User $actor): ?DocumentReview
    {
        return $this->document->reviews()
            ->where('Status', DocumentReviewStatusEnum::Pending->value)
            ->where('CreatedBy', $actor->Id)
            ->latest('Id')
            ->first();
    }

    /**
     * Document-wide freeze: true while any review round (by any requester) is still pending,
     * regardless of who is asking. Mirrors isHold() - used to lock the document down until the
     * reviewer marks it reviewed.
     */
    public function isUnderReview(): bool
    {
        return $this->document->reviews()->where('Status', DocumentReviewStatusEnum::Pending->value)->exists();
    }

    /**
     * Why the file can't be changed right now, or null when it can. Ordered by how firm the
     * block is: a legal hold or review is a decision by someone else, a checkout is just
     * someone mid-edit. The codes are stable - external systems branch on them.
     *
     * @throws ErroredException
     */
    public function lockReason(): ?string
    {
        return match (true) {
            $this->isHold() => 'legal_hold',
            $this->isUnderReview() => 'under_review',
            $this->isArchived() => 'archived',
            $this->isCheckedOut() === 1 => 'checked_out',
            $this->isPendingValidation() => 'pending_validation',
            default => null,
        };
    }

    /**
     * 0. Not checked out
     * 1. checked out.
     * 3. @param User|null $actor checked out
     * @return int
     */
    public function isCheckedOut(?User $actor = null): int
    {
        // No type gate here: normally only checkout()-capable types ever have checkout rows,
        // but forceCheckout() (e.g. after a review completes) can check out any type, and that
        // state still needs to be reported accurately.
        if (is_null($actor)) {
            return $this->document->checkouts()->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)->exists() ? 1 : 0;
        }


        if ($this->document->checkouts()->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)->where('t_DocumentCheckOuts.CreatedBy', $actor->Id)->exists()) {
            return 2;
        }


        return $this->isCheckedOut();
    }

    /**
     * @throws ErroredException
     */
    public function checkin(UploadedFile $file, User $actor, string $remark = ''): static
    {
        if ($this->isHold()) {
            throw new ErroredException('document is on legal hold !');
        }
        if ($this->isUnderReview()) {
            throw new ErroredException('document is under review !');
        }
        if ($this->isArchived()) {
            throw new ErroredException('document is archived !');
        }
        $checkOut = $this->document->checkouts()->where('Status', DocumentCheckOutStatusEnum::CheckOut->value)->where('t_DocumentCheckOuts.CreatedBy', $actor->Id)->first();
        if (! $checkOut instanceof DocumentCheckOut) {
            throw new ErroredException('you don\'t have an active checkout for this document.');
        }
        $extension = ExtensionsEnum::fromMimeType($file->getMimeType() ?? $file->getClientMimeType());
        $disk = self::getDisk();
        $checksum1 = hash_file(self::CHECKSUM, $file->getRealPath());
        $path = self::_saveFile($disk, $file->getContent());
        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));

        try {
            return DB::transaction(function () use ($file, $actor, $remark, $checkOut, $path, $checksum1, $checksum2, $extension, $disk) {
                $checkOut->update([
                    'Status' => DocumentCheckOutStatusEnum::CheckIn->value,
                    'Dated' => now(),
                    'CheckInRemark' => $remark,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($this->document)->event('checked-in')->log('document checked in');

                return $this->_newVersion($disk, $path, $file->getClientOriginalName(), $file->getSize(), $actor, properties: (new UploadFileProperties($file, $extension))->getProperties(), checksum: base64_encode($checksum1 . '|' . $checksum2), extension: $extension, description: $remark);
            });
        } catch (Throwable $e) {
            Log::error('Error checking in document: ' . $e);
        }

        throw new ErroredException('checking in document failed');
    }

    /**
     * Used for conversions and signing
     * @throws ErroredException
     */
    public function newVersionFile(string $filePath, User $actor): static
    {
        if (! file_exists($filePath)) {
            throw new ErroredException('File does not exist. !' . $filePath);
        }

        $extension = ExtensionsEnum::fromMimeType(mime_content_type($filePath));
        $disk = self::getDisk();
        $checksum1 = hash_file(self::CHECKSUM, $filePath);
        $path = self::_saveFile($disk, file_get_contents($filePath));
        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));
        $size = (int)filesize($filePath);
        $name = /*"Signed " .*/ pathinfo($this->document->Name, PATHINFO_FILENAME) . '.' . $extension->value;
        unlink($filePath);

        return $this->_newVersion($disk, $path, $name, $size, $actor, checksum: base64_encode($checksum1 . '|' . $checksum2), extension: $extension);
    }

    /**
     * @throws ErroredException
     */
    public function newVersionUpload(UploadedFile $file, User $actor, string $Related, string|int $RelatedId): static
    {
        //check can change version.
        if ($this->document->userRole($actor, [RoleEnum::Admin->value, RoleEnum::Write->value, RoleEnum::Share->value])->doesntExist()) {
            throw new ErroredException('You do not have permission to change this document.');
        }

        if ($this->document->relations()->where('t_DocumentRelations.Related', $Related)->where('t_DocumentRelations.RelatedID', $RelatedId)->doesntExist()) {
            throw new ErroredException('You do not have permission to change this document.');
        }

        //todo check if held or pending signing.
        $extension = ExtensionsEnum::fromMimeType($file->getMimeType() ?? $file->getClientMimeType());
        $disk = self::getDisk();
        $checksum1 = hash_file(self::CHECKSUM, $file->getRealPath());
        $path = self::_saveFile($disk, $file->getContent());
        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));

        return $this->_newVersion($disk, $path, $file->getClientOriginalName(), $file->getSize(), $actor, properties: (new UploadFileProperties($file, $extension))->getProperties(), checksum: base64_encode($checksum1 . '|' . $checksum2), extension: $extension);
    }

    /**
     * A replacement file from an external system. Unlike newVersionUpload() there is no ERP
     * user or relation to check - the caller has already been matched to the document's
     * source system - but every lock (legal hold, review, archive, checkout, pending
     * validation) is enforced here, so no caller can route around them.
     *
     * @throws ErroredException
     */
    public function newVersionExternal(UploadedFile $file, User $actor, string $remark = ''): static
    {
        $reason = $this->lockReason();
        if ($reason !== null) {
            throw new ErroredException('document cannot be changed: ' . str_replace('_', ' ', $reason) . '.');
        }

        $extension = ExtensionsEnum::fromMimeType($file->getMimeType() ?? $file->getClientMimeType());
        $disk = self::getDisk();
        $checksum1 = hash_file(self::CHECKSUM, $file->getRealPath());
        $path = self::_saveFile($disk, $file->getContent());
        $checksum2 = hash_file(self::CHECKSUM, Storage::disk($disk->value)->path($path));

        return DB::transaction(fn () => $this->_newVersion(
            $disk, $path, $file->getClientOriginalName(), $file->getSize(), $actor,
            properties: (new UploadFileProperties($file, $extension))->getProperties(),
            checksum: base64_encode($checksum1 . '|' . $checksum2),
            extension: $extension,
            description: $remark !== '' ? $remark : 'Replaced from external system',
        ));
    }

    public function validateToken(User $user, string $token): bool
    {
        if ((string)Cache::get($user->Id . '-download-' . $this->document->Id) !== $token) {
            return false;
        }

        $Key = Crypt::decryptString($token);
        if ($Key === false) {
            return false;
        }
        $key = explode('|', base64_decode($Key));
        if (count($key) !== 4) {
            return false;
        }
        $expire = new DateTime();
        $expire->setTimestamp($key[2]);

        return ($expire > now() && $key[0] === $user->UserID && $key[1] === $this->document->DocumentId);
    }

    public function generateToken(User $user): string
    {
        $expire = now()->addMinutes(60);
        $key = base64_encode($user->UserID . '|' . $this->document->DocumentId . '|' . $expire->copy()->timestamp . '|' . Str::random(64));

        $encryptedKey = Crypt::encryptString($key);

        Cache::put($user->Id . '-download-' . $this->document->Id, $encryptedKey, $expire);

        return $encryptedKey;
    }

    /**
     * @throws ErroredException
     */
    public function preview(string $attr): string
    {
        if (! $this->isPrevieable()) {
            return '';
        }

        if ($this->type->value === ExtensionsEnum::Txt->value) {
            return '<textarea readonly disabled ' . $attr . '>' . $this->getFileContent(false) . '</textarea>';
        }

        // Images/video/PDF: point the browser at the raw bytes instead of embedding a base64
        // data URI in the HTML response. Base64 adds ~33% size on top of building the whole
        // (decrypted) file as a PHP string, which is what made large files slow enough to trip
        // client-side timeouts for anyone without a fast connection to the server.
        $rawUrl = URL::temporarySignedRoute('dms.file.raw', now()->addMinutes(15), ['document' => $this->document->DocumentId]);

        if ($this->type->isImage()) {
            $mimeType = $this->document->MimeType ?: $this->type->getMimeType();

            return '<img src="data:' . $mimeType . ';base64,' . $this->getFileContent() . '" ' . $attr . ' >';
        }

        if ($this->type->isVideo()) {
            return '<video controls src="' . $rawUrl . '" ' . $attr . '>Sorry, your browser doesn\'t support embedded videos</video>';
        }

        if ($this->type->value === ExtensionsEnum::Pdf->value) {
            return '<iframe src="' . $rawUrl . '#toolbar=0&navpanes=0" ' . $attr . '></iframe>';
        }

        if ($this->type->isDocument()) {
            $previewPdfUrl = URL::temporarySignedRoute('dms.file.preview-pdf', now()->addMinutes(15), ['document' => $this->document->DocumentId]);

            return '<iframe src="' . $previewPdfUrl . '#toolbar=0&navpanes=0" ' . $attr . '></iframe>';
        }

        return '';
    }

    /**
     * Same as preview(), but for a specific historical version rather than the current one -
     * used by the version history panel so an older version can be viewed without checking it
     * back out or overwriting the current file.
     * @throws ErroredException
     */
    public function previewVersion(DocumentVersion $version, string $attr): string
    {
        $type = $this->typeOf($version);
        if (! $type->isPreview()) {
            return '';
        }

        if ($type->value === ExtensionsEnum::Txt->value) {
            return '<textarea readonly disabled ' . $attr . '>' . $this->getFileContent(false, $version) . '</textarea>';
        }

        $rawUrl = URL::temporarySignedRoute('dms.file.version-raw', now()->addMinutes(15), ['document' => $this->document->DocumentId, 'version' => $version->Id]);

        if ($type->isImage()) {
            return '<img src="data:' . $type->getMimeType() . ';base64,' . $this->getFileContent(true, $version) . '" ' . $attr . ' >';
        }

        if ($type->isVideo()) {
            return '<video controls src="' . $rawUrl . '" ' . $attr . '>Sorry, your browser doesn\'t support embedded videos</video>';
        }

        if ($type->value === ExtensionsEnum::Pdf->value) {
            return '<iframe src="' . $rawUrl . '#toolbar=0&navpanes=0" ' . $attr . '></iframe>';
        }

        if ($type->isDocument()) {
            $previewPdfUrl = URL::temporarySignedRoute('dms.file.version-preview-pdf', now()->addMinutes(15), ['document' => $this->document->DocumentId, 'version' => $version->Id]);

            return '<iframe src="' . $previewPdfUrl . '#toolbar=0&navpanes=0" ' . $attr . '></iframe>';
        }

        return '';
    }

    public function isPrevieable(): bool
    {
        return $this->type->isPreview();
    }

    /**
     * A version's file can differ in type from the document's current MimeType (e.g. a
     * check-in replaces a doc with a different format, or signing produces a PDF), so this is
     * derived from that version's own stored file name rather than $this->type.
     */
    public function typeOf(DocumentVersion $version): ExtensionsEnum
    {
        return ExtensionsEnum::tryFrom(strtolower(pathinfo($version->Name, PATHINFO_EXTENSION))) ?? ExtensionsEnum::None;
    }

    /**
     * @throws ErroredException
     */
    public function getFileContent(bool $base64 = true, ?DocumentVersion $version = null): string
    {
        $version ??= $this->document->current;
        if (! $version instanceof DocumentVersion) {
            throw new ErroredException('No file found, decrypting the file.');
        }

        $disk = Storage::disk($version->Disk->value);
        if (! $disk->exists($version->Path)) {
            throw new ErroredException('File not found on storage disk.');
        }

        $encryptedContent = $disk->get($version->Path);
        if (is_null($encryptedContent) || $encryptedContent === false) {
            throw new ErroredException('Unable to read file content.');
        }

        $content = (new EncryptionService())->decrypt($encryptedContent);

        return ($base64) ? base64_encode($content) : $content;
    }

    /**
     * @throws ErroredException
     */
    public function getTempPath(?DocumentVersion $version = null): ?string
    {
        $extension = $version instanceof DocumentVersion ? $this->typeOf($version) : $this->document->ext();
        $name = Uuid::uuid4()->toString() . '.' . $extension->value;
        if (Storage::disk('temp')->put($name, $this->getFileContent(false, $version))) {
            return Storage::disk('temp')->path($name);
        }

        return null;
    }

    public function html(): string
    {
        return '<tr> <td> <div class="d-flex align-items-center"><img src="' . $this->document->ext()?->getIcon('img') . '" alt="ICO" class="wid-35">
                <h6 class="mb-0 ms-2 text-truncate">' . $this->document->Name . '</h6> </div> </td> <td>' . Number::fileSize($this->document->current->Size, 2) . '</td> <td>' . $this->document->current->ModifiedOn->format('d M Y H:i') . '</td>
                <td> <div class="user-group p-1">' . $this->_usersHtml() . '</div> </td>
                <td> <div class="d-flex flex-wrap gap-2"> ' . $this->_tagsHtml() . ' </div> </td> <td> <ul class="list-inline text-end">
                <li class="list-inline-item"> ' . $this->_permissionHtml() . ' </li>
                <li class="list-inline-item"> <a href="#" class="btn btn-outline-info btn-sm"> <i data-feather="eye" class="text-info"></i> details</a> </li> </ul> </td> </tr>';
    }

    private function _usersHtml(): string
    {
        if ($this->document->Visibility->value === VisibilityEnum::Public->value) {
            return $this->document->creator?->getImage('alt="user-image" class="avtar"') . '<span class="avtar avtar-xs bg-light-primary text-primary">+' . User::count() . '</span>';
        }

        $users = User::query()->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->whereIn('t_Users.Id', $this->document->permissions()->where('Party', User::getPrimaryKey())->select('PartyID'));
            })->orWhereHas('teams', function (Builder $query) {
                $query->whereIn('t_Teams.TeamID', $this->document->permissions()->where('Party', Team::getPrimaryKey())->select('PartyID'));
            });
        })->paginate(5);

        return $this->document->creator?->getImage('alt="user-image" class="avtar"') . '<span class="avtar avtar-xs bg-light-primary text-primary">+' . $users->total() . '</span>';
    }

    private function _tagsHtml(): string
    {
        return '';
    }

    private function _permissionHtml(): string
    {
        return ($this->document->Visibility->value === VisibilityEnum::Private->value)
            ? '<i data-feather="lock" title="Private" class="text-danger icon-size"></i>'
            : '<i data-feather="globe" title="Public" class="text-primary icon-size"></i> ';
    }

    public function tags(User $user)
    {
        return $this->document->tags()->user($user);
    }

    public function users(): Builder
    {
        if ($this->document->Visibility->value === VisibilityEnum::Public->value) {
            return User::query()->where('UserID', '!=', SystemHelper::ID);
        }

        return User::query()->where('UserID', '!=', SystemHelper::ID)->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->whereIn('t_Users.Id', $this->document->permissions()->where('Party', User::getPrimaryKey())->select('PartyID'));
            })->orWhereHas('teams', function (Builder $query) {
                $query->whereIn('t_Teams.TeamID', $this->document->permissions()->where('Party', Team::getPrimaryKey())->select('PartyID'));
            });
        });
    }

    /**
     * @throws ErroredException
     */
    public function visibility(VisibilityEnum $visibility, User $actor): static
    {
        try {
            return DB::transaction(function () use ($visibility, $actor) {
                $this->document->update([
                    'Visibility' => $visibility->value,
                    'ModifiedBy' => $actor->Id,
                ]);

                activity()->causedBy($actor)->performedOn($this->document)->event('update')->log('Updated file ' . $this->document->Name . ' visibility : ' . $visibility->description());

                return $this;
            });
        } catch (Exception | Throwable $e) {
            Log::error('Error update document visibility: ');
            Log::error($e);

            throw new ErroredException();
        }
    }

    /**
     * @throws ErroredException
     */
    public function removePermission(SpecialPermission $permission, User $actor): static
    {
        $this->document = $this->_trashPermissions($this->document, $permission, $actor);

        return $this;
    }

    public function summaryList(bool $withTrash = false, bool $refreshOnDelete = false): string
    {
        $parameters = [];
        if ($withTrash) {
            $parameters = ['trash' => 'yes'];
            if ($refreshOnDelete) {
                $parameters['trashRefresh'] = 'yes';
            }
        }

        return '<span class="btn btn-outline-info modal-preview-document" title="' . $this->document->Name . '"
                        data-url="' .  route('file.embed-preview', array_merge([$this->document->DocumentId], $parameters)) . '" id="document-' . $this->document->DocumentId . '">
                    ' . $this->document->ext()?->getIcon() . "&nbsp;" . Str::limit(explode(".", $this->document->Name)[0], 10) . '.' . $this->document->ext()?->value . '</span>';
    }

    public function restore(User $actor): static
    {
        if (! $this->document->trashed()) {
            return $this;
        }

        $this->document->forceFill([
            'DeletedOn' => null,
            'DeletedBy' => null,
        ])->save();
        activity()->causedBy($actor)->performedOn($this->document)->event('restore')->log("document {$this->document->Name} restored");

        return $this;
    }
}
