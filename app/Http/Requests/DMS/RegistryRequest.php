<?php

namespace App\Http\Requests\DMS;

use App\Enums\Core\VisibilityEnum;
use App\Models\Auth\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegistryRequest extends FormRequest
{
    /**
     * Authorisation is the controller's job: it needs the policy and the
     * head-office check together.
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
            // Auto-generated on create (RegistryService::generateCode()), so it's only
            // ever posted when renaming an existing registry via update().
            'Code' => [
                $this->isCreating() ? 'nullable' : 'required',
                'string', 'max:20', 'regex:/^[A-Za-z0-9._-]+$/',
            ],
            'Name' => ['required', 'string', 'max:150'],
            'Description' => ['nullable', 'string', 'max:255'],
            'ParentRegistryId' => ['nullable', 'integer', $this->liveRegistryRule()],
            'BranchId' => ['nullable', 'integer', Rule::exists('t_Branches', 'Id')->whereNull('DeletedOn')],
            // The shared users.select2 endpoint returns the string UserID, not
            // the numeric key, so that is what the form posts; payload()
            // resolves it to the Id the FK actually wants.
            'HodUserID' => ['required', 'string', Rule::exists('t_Users', 'UserID')->whereNull('DeletedOn')],
            // Not user-chosen yet - RegistryService assigns/preserves a default until
            // the approval workflow brings its own statuses.
            'IsArchive' => ['nullable', 'boolean'],
            'Visibility' => ['required', Rule::in(array_column(VisibilityEnum::cases(), 'value'))],
        ];
    }

    private function isCreating(): bool
    {
        return $this->route('registry') === null;
    }

    /**
     * Validated data with the custodian resolved to a numeric user key.
     *
     * @return array<string,mixed>
     */
    public function payload(): array
    {
        $data = $this->validated();

        $data['HodUserId'] = (int) User::query()
            ->where('UserID', $data['HodUserID'])
            ->whereNull('DeletedOn')
            ->value('Id');

        unset($data['HodUserID']);

        return $data;
    }

    /**
     * Restricts the parent to a registry that has not been soft-deleted.
     */
    private function liveRegistryRule(): object
    {
        return Rule::exists('t_DMSRegistries', 'Id')->whereNull('DeletedOn');
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'Code.regex' => 'The code may only contain letters, digits, dot, dash or underscore.',
            'HodUserID.exists' => 'Select a valid custodian.',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return [
            'HodUserID' => 'head of registry',
            'ParentRegistryId' => 'parent registry',
            'BranchId' => 'branch',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['IsArchive' => $this->boolean('IsArchive')]);
    }
}
