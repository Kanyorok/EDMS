<?php

namespace App\Http\Requests\DMS;

use App\Enums\Core\ExtensionsEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadPhysicalDocumentSoftCopyRequest extends FormRequest
{
    /**
     * Filing a scan against an already-registered physical document only
     * needs the target repository and the file itself - the paper record's
     * classification (linked entity, document type, folio) was already
     * captured when it was registered, so this doesn't re-collect it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'repository' => ['required', 'integer'],
            'file' => [
                'required', 'bail',
                Rule::file()->types(ExtensionsEnum::getAllMimeTypes())->max((int) bcmul(config('app.dms.file_size'), 1024, 0)),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'repository.required' => 'Choose a repository you have access to.',
            'file.required' => 'A file must be uploaded.',
            'file.types' => 'The uploaded file is not allowed.',
            'file.max' => 'The uploaded file exceeds the maximum size allowed.',
        ];
    }
}
