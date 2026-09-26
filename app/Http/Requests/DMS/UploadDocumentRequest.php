<?php

namespace App\Http\Requests\DMS;

use App\Enums\Core\ExtensionsEnum;
use App\Http\Controllers\DMS\Files\DocumentRelationOptionsController;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\LinkedToType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadDocumentRequest extends FormRequest
{
    protected ?DMSDocumentType $documentType = null;
    protected ?LinkedToType $linkedToType = null;

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('FolioId')) {
            $folioId = $this->input('FolioId');
            if (is_null($folioId) || $folioId === '' || $folioId === 'null' || $folioId === 'undefined' || ! is_numeric($folioId)) {
                $this->merge(['FolioId' => null]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The file's allowed types and max size come from the chosen document
     * type's own rules rather than the module-wide defaults, since picking a
     * document type is what "the rules are obeyed" means for this endpoint.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $documentType = $this->resolvedDocumentType();

        $size = $documentType
            ? (int) bcmul($documentType->MaxSizeMB, 1024, 0)
            : (int) bcmul(config('app.dms.file_size'), 1024, 0);

        // A type that names extensions is held to them; one that names none allows every type. A type whose
        // extensions are all unknown allows nothing, rather than silently everything.
        $mimeTypes = $documentType && $documentType->extensions()->isNotEmpty()
            ? ($documentType->mimeTypes() ?: ['application/x-no-supported-type'])
            : ExtensionsEnum::getAllMimeTypes();

        return [
            'LinkedToTypeId' => [
                'required', 'integer',
                Rule::exists('t_DMSLinkedToTypes', 'Id')->where('IsActive', true)->whereNull('DeletedOn'),
            ],
            'Related' => ['required', Rule::in(array_keys(DocumentRelationOptionsController::RELATION_TYPES))],
            'LinkedId' => ['required', 'string', 'max:100'],
            'DocumentTypeId' => [
                'required', 'integer',
                Rule::exists('t_DMSDocumentTypes', 'Id')->where('IsActive', true)->whereNull('DeletedOn'),
            ],
            'FolioId' => ['nullable', 'integer', Rule::exists('t_DMSFolios', 'Id')->whereNull('DeletedOn')],
            'ExpiryDate' => [$documentType?->ExpiryRequired ? 'required' : 'nullable', 'date', 'after:today'],
            'Notes' => ['nullable', 'string', 'max:2000'],
            'file' => [
                'required', 'bail',
                Rule::file()->types($mimeTypes)->max($size),
            ],
        ];
    }

    /**
     * Cross-field checks the spec calls out as the DMS's most common
     * integrity failure point: a Linked-to Type that isn't in the chosen
     * Document Type's applicability, or a Related lookup that doesn't match
     * the chosen Linked-to Type's own source relation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $documentType = $this->resolvedDocumentType();
            $linkedToType = $this->resolvedLinkedToType();

            if ($documentType && $linkedToType && $documentType->linkedToTypes()->exists()
                && ! $documentType->linkedToTypes()->whereKey($linkedToType->Id)->exists()) {
                $validator->errors()->add('LinkedToTypeId', "{$linkedToType->Name} is not a valid link for document type {$documentType->Code}.");
            }

            if ($linkedToType?->SourceRelation && $this->string('Related')->toString() !== $linkedToType->SourceRelation) {
                $validator->errors()->add('Related', "{$linkedToType->Name} records are looked up as " . DocumentRelationOptionsController::RELATION_TYPES[$linkedToType->SourceRelation] . '.');
            }
        });
    }

    protected function resolvedDocumentType(): ?DMSDocumentType
    {
        return $this->documentType ??= DMSDocumentType::query()
            ->whereKey($this->integer('DocumentTypeId'))
            ->whereNull('DeletedOn')
            ->first();
    }

    protected function resolvedLinkedToType(): ?LinkedToType
    {
        return $this->linkedToType ??= LinkedToType::query()
            ->whereKey($this->integer('LinkedToTypeId'))
            ->whereNull('DeletedOn')
            ->first();
    }

    public function messages(): array
    {
        return [
            'LinkedToTypeId.required' => 'Select what this document is linked to.',
            'Related.required' => 'Link a Client, Lead or Employee record.',
            'LinkedId.required' => 'Link a Client, Lead or Employee record.',
            'DocumentTypeId.required' => 'Select a document type.',
            'FolioId.exists' => 'Selected folio could not be found.',
            'ExpiryDate.required' => 'This document type requires an expiry date.',
            'ExpiryDate.after' => 'Expiry date must be in the future.',
            'file.required' => 'A file must be uploaded.',
            'file.mimetypes' => 'The type of file you uploaded is not permitted for this document type.',
            'file.types' => 'The uploaded file is not allowed for this document type.',
            'file.max' => 'The uploaded file exceeds the maximum size allowed for this document type.',
        ];
    }
}
