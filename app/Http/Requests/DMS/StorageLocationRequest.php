<?php

namespace App\Http\Requests\DMS;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class StorageLocationRequest extends FormRequest
{
    /**
     * Authorisation is handled in the controller: it needs both the policy and
     * the branch check, and the branch check depends on the resolved registry.
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
        return [
            // Kept in step with StorageLocationService::CODE_PATTERN - the code
            // becomes a Path segment, and Path is matched with LIKE.
            'Code' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'],
            'Label' => ['nullable', 'string', 'max:150'],
            'ParentId' => ['nullable', 'integer'],
            // Each id must come from its own t_CodeDetails bucket - a plain
            // exists() would let a purpose id be submitted as a type.
            'LocationTypeId' => ['required', 'integer', $this->inBucket('LocationTypeId')],
            'LocationPurposeId' => ['required', 'integer', $this->inBucket('LocationPurposeId')],
            'LocationStatusId' => ['required', 'integer', $this->inBucket('LocationStatusId')],
            'Barcode' => ['nullable', 'string', 'max:50'],
            'Capacity' => ['nullable', 'integer', 'min:0'],
            'SortOrder' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Constrains a lookup id to one t_CodeDetails bucket.
     */
    private function inBucket(string $bucket): Exists
    {
        return Rule::exists('t_CodeDetails', 'ID')
            ->where('CodeID', $bucket)
            ->whereNull('DeletedOn');
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'Code.regex' => 'The code may only contain letters, digits, dot, dash or underscore.',
            'LocationTypeId.exists' => 'Select a valid location type.',
            'LocationPurposeId.exists' => 'Select a valid purpose.',
            'LocationStatusId.exists' => 'Select a valid status.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return [
            'LocationTypeId' => 'type',
            'LocationPurposeId' => 'purpose',
            'LocationStatusId' => 'status',
            'ParentId' => 'parent location',
        ];
    }
}
