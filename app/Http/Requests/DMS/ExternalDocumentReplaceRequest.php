<?php

namespace App\Http\Requests\DMS;

use App\Models\DMS\Document;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An external system replacing the file of a document it filed. The new file is held to the
 * same document type rules as the original.
 */
class ExternalDocumentReplaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Document|null $document */
        $document = $this->attributes->get('document');

        return [
            'actor' => ['required', 'string', 'max:100'],
            'actorName' => ['nullable', 'string', 'max:150'],
            'remark' => ['nullable', 'string', 'max:500'],
            'file' => ['required', 'bail', ...ExternalDocumentUploadRequest::fileRules($document?->documentType)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file must be uploaded.',
            'file.types' => 'The uploaded file is not allowed for this document type.',
            'file.max' => 'The uploaded file exceeds the maximum size allowed for this document type.',
        ];
    }
}
