<?php

namespace App\Http\Requests\DMS;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentTypeRequest extends FormRequest
{
    /**
     * Authorisation is the controller's job, via the DMSDocumentType policy.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('documentType')?->Id;

        return [
            'Code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('t_DMSDocumentTypes', 'Code')->ignore($id, 'Id')->whereNull('DeletedOn'),
            ],
            'Name' => ['required', 'string', 'max:150'],
            'RepositoryId' => ['nullable', 'integer', Rule::exists('t_Repositories', 'Id')->whereNull('DeletedOn')],
            'TagId' => ['nullable', 'integer', Rule::exists('t_DMSTags', 'Id')->whereNull('DeletedOn')],
            'EntityApplicability' => [
                'nullable', 'string', 'max:255',
                Rule::exists('t_CodeDetails', 'Description')->where('CodeID', 'DMSEntityApplicabilityId')->where('IsActive', true)->whereNull('DeletedOn'),
            ],
            'LinkedToTypeIds' => ['nullable', 'array'],
            'LinkedToTypeIds.*' => [
                'integer',
                Rule::exists('t_DMSLinkedToTypes', 'Id')->where('IsActive', true)->whereNull('DeletedOn'),
            ],
            'AllowedExtensions' => ['nullable', 'string', 'max:500'],
            'MaxSizeMB' => ['required', 'integer', 'min:1', 'max:1024'],
            'ExpiryRequired' => ['nullable', 'boolean'],
            'IsActive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $extensions = collect(is_array($this->input('AllowedExtensions')) ? $this->input('AllowedExtensions') : explode(',', (string) $this->input('AllowedExtensions')))
            ->map(fn ($ext) => Str::of((string) $ext)->trim()->ltrim('.')->lower()->toString())
            ->filter()
            ->unique()
            ->implode(',');

        $this->merge([
            'Code' => Str::of((string) $this->input('Code'))->trim()->toString(),
            'AllowedExtensions' => $extensions,
            'MaxSizeMB' => $this->input('MaxSizeMB') ?: 10,
            'ExpiryRequired' => $this->boolean('ExpiryRequired'),
            'IsActive' => $this->boolean('IsActive', true),
        ]);
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'Code.regex' => 'The document type ID may only contain letters, digits, dot, dash or underscore.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return [
            'Code' => 'document type ID',
            'RepositoryId' => 'category',
            'TagId' => 'record classification',
        ];
    }
}
