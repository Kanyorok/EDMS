<?php

namespace App\Http\Requests\DMS;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceSystemRequest extends FormRequest
{
    /**
     * Authorisation is the controller's job, via the SourceSystem policy.
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
        $id = $this->route('sourceSystem')?->Id;

        return [
            'Code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                // Soft-deleted rows still hold the value in the table's unique index.
                Rule::unique('t_DMSSourceSystems', 'Code')->ignore($id, 'Id'),
            ],
            'Name' => ['required', 'string', 'max:150'],
            'Description' => ['nullable', 'string', 'max:255'],
            'ServiceUserId' => ['required', 'integer', Rule::exists('t_Users', 'Id')->whereNull('DeletedOn')],
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
            'ServiceUserId.exists' => 'Pick an existing user to act as the service account.',
        ];
    }
}
