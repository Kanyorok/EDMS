<?php

namespace App\Http\Requests\DMS;

use App\Enums\Core\ExtensionsEnum;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\EventType;
use App\Models\DMS\SourceSystem;
use App\Services\DMS\EventCatalogService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * An external system filing a document against one of its own records.
 *
 * The event must be one the system may raise from the given module (re-checked here, not
 * just offered in the catalog), and the file is held to that event's document type: allowed
 * types, size and, when the type requires it, an expiry date.
 */
class ExternalDocumentUploadRequest extends FormRequest
{
    private ?SourceSystem $system = null;
    private ?EventType $event = null;
    private bool $eventResolved = false;
    private ?DMSDocumentType $documentType = null;
    private bool $documentTypeResolved = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $documentType = $this->resolvedDocumentType();

        return [
            'event' => ['required', 'string', 'max:50'],
            'documentType' => ['nullable', 'string', 'max:50'],
            'module' => ['required', 'integer', 'min:1'],
            'entityId' => ['required', 'string', 'max:100'],
            'actor' => ['required', 'string', 'max:100'],
            'actorName' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expiryDate' => [$documentType?->ExpiryRequired ? 'required' : 'nullable', 'date', 'after:today'],
            'properties' => ['nullable', 'array'],
            'file' => ['required', 'bail', ...self::fileRules($documentType)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('event') && $this->filled('module') && $this->resolvedEvent() === null) {
                $validator->errors()->add('event', 'This event is not available from that module.');

                return;
            }

            if ($this->resolvedEvent() !== null && $this->resolvedDocumentType() === null) {
                $validator->errors()->add('documentType', $this->filled('documentType')
                    ? 'That document type is not available for this event.'
                    : 'Choose which document this is.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        // A multipart caller (the gateway) finds it easier to send properties as one JSON field.
        if (is_string($this->input('properties'))) {
            $decoded = json_decode($this->input('properties'), true);
            $this->merge(['properties' => is_array($decoded) ? $decoded : []]);
        }
    }

    public function sourceSystem(): SourceSystem
    {
        return $this->system ??= $this->attributes->get('sourceSystem');
    }

    public function resolvedEvent(): ?EventType
    {
        if (! $this->eventResolved) {
            $this->eventResolved = true;
            $this->event = ($this->filled('event') && $this->filled('module'))
                ? app(EventCatalogService::class)->resolve($this->sourceSystem(), (string) $this->input('event'), (int) $this->input('module'))
                : null;
        }

        return $this->event;
    }

    /**
     * The document type the file is filed under: the one named, if this event offers it, or the only one the event
     * has when none is named (so a caller of an event with a single type need not say which).
     */
    public function resolvedDocumentType(): ?DMSDocumentType
    {
        if (! $this->documentTypeResolved) {
            $this->documentTypeResolved = true;
            $types = $this->resolvedEvent()?->documentTypes;

            $this->documentType = match (true) {
                $types === null => null,
                $this->filled('documentType') => $types->firstWhere('Code', (string) $this->input('documentType')),
                $types->count() === 1 => $types->first(),
                default => null,
            };
        }

        return $this->documentType;
    }

    /**
     * Allowed types and size come from the document type's own rules.
     *
     * @return array<int, mixed>
     */
    public static function fileRules(?DMSDocumentType $documentType): array
    {
        $size = $documentType
            ? (int) bcmul((string) $documentType->MaxSizeMB, '1024', 0)
            : (int) bcmul((string) config('app.dms.file_size'), '1024', 0);

        // A type that names extensions is held to them; one that names none allows every type. A type whose
        // extensions are all unknown allows nothing, rather than silently everything.
        $mimeTypes = $documentType && $documentType->extensions()->isNotEmpty()
            ? ($documentType->mimeTypes() ?: ['application/x-no-supported-type'])
            : ExtensionsEnum::getAllMimeTypes();

        return [Rule::file()->types($mimeTypes)->max($size)];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expiryDate.required' => 'This document type requires an expiry date.',
            'expiryDate.after' => 'Expiry date must be in the future.',
            'file.required' => 'A file must be uploaded.',
            'file.types' => 'The uploaded file is not allowed for this document type.',
            'file.max' => 'The uploaded file exceeds the maximum size allowed for this document type.',
        ];
    }
}
