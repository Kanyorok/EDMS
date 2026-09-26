<?php

namespace App\Http\Requests\DMS;

use App\Http\Controllers\DMS\Files\DocumentRelationOptionsController;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkedToTypeRequest extends FormRequest
{
    /**
     * Authorisation is the controller's job, via the LinkedToType policy.
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
        $id = $this->route('linkedToType')?->Id;

        return [
            'Code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                Rule::unique('t_DMSLinkedToTypes', 'Code')->ignore($id, 'Id')->whereNull('DeletedOn'),
            ],
            'Name' => ['required', 'string', 'max:150'],
            'Description' => ['nullable', 'string', 'max:255'],
            'SourceRelation' => ['nullable', Rule::in(array_keys(DocumentRelationOptionsController::RELATION_TYPES))],
            'IsActive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['IsActive' => $this->boolean('IsActive', true)]);
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'Code.regex' => 'The code may only contain letters, digits, dot, dash or underscore.',
        ];
    }
}
