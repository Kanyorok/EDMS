<?php

namespace App\Http\Requests\DMS;

use App\Enums\Core\DataTypesEnum;
use App\Models\DMS\DMSDocumentType;
use App\Models\DMS\SourceSystem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EventTypeRequest extends FormRequest
{
    /**
     * Authorisation is the controller's job, via the EventType policy.
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
        $id = $this->route('eventType')?->Id;
        $sourceSystemId = $this->input('SourceSystemId');

        return [
            'SourceSystemId' => ['required', 'integer', Rule::exists('t_DMSSourceSystems', 'Id')->whereNull('DeletedOn')],
            'Code' => [
                'required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/',
                // Soft-deleted rows still hold the value in the table's unique index.
                Rule::unique('t_DMSEventTypes', 'Code')
                    ->where('SourceSystemId', $sourceSystemId)
                    ->ignore($id, 'Id'),
            ],
            'Name' => ['required', 'string', 'max:150'],
            'Description' => ['nullable', 'string', 'max:255'],
            'DocumentTypeIds' => ['required', 'array', 'min:1'],
            'DocumentTypeIds.*' => ['integer', 'distinct', Rule::exists('t_DMSDocumentTypes', 'Id')->whereNull('DeletedOn')],
            'LinkedToTypeId' => ['nullable', 'integer', Rule::exists('t_DMSLinkedToTypes', 'Id')->whereNull('DeletedOn')],
            'ExternalEntityType' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_]+$/'],
            'AllowReplace' => ['nullable', 'boolean'],
            'ReviewerUserId' => ['nullable', 'integer', Rule::exists('t_Users', 'Id')->whereNull('DeletedOn')],
            'ReviewerTeamId' => ['nullable', 'integer', Rule::exists('t_Teams', 'TeamID')->whereNull('DeletedOn')],
            'ValidationTypeId' => ['nullable', 'integer', Rule::exists('t_DocumentValidationTypes', 'Id')->whereNull('DeletedOn')],
            'IsActive' => ['nullable', 'boolean'],

            'Modules' => ['nullable', 'array'],
            'Modules.*' => ['integer', 'min:1', 'distinct'],

            'Properties' => ['nullable', 'array'],
            'Properties.*.Name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'Properties.*.Label' => ['required', 'string', 'max:150'],
            'Properties.*.DataType' => ['required', Rule::in(DataTypesEnum::values())],
            'Properties.*.IsRequired' => ['nullable', 'boolean'],
            'Properties.*.Options' => ['nullable', 'array'],
            'Properties.*.Options.*.value' => ['required', 'string', 'max:100'],
            'Properties.*.Options.*.label' => ['required', 'string', 'max:150'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $modules = collect($this->input('Modules', []))
            ->map(fn ($m) => is_numeric($m) ? (int) $m : $m)
            ->all();

        // The form sends a list property's options as one "VALUE=Label" pair per line.
        $properties = collect($this->input('Properties', []))
            ->values()
            ->map(function ($property) {
                if (! is_array($property)) {
                    return $property;
                }

                $property['IsRequired'] = filter_var($property['IsRequired'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if (isset($property['Options']) && is_string($property['Options'])) {
                    $property['Options'] = $this->parseOptions($property['Options']);
                }
                if (($property['DataType'] ?? null) !== DataTypesEnum::List->value) {
                    $property['Options'] = null;
                }

                return $property;
            })
            ->all();

        // Blank selects arrive as empty strings; they mean "none".
        $this->merge(collect(['ReviewerUserId', 'ReviewerTeamId', 'ValidationTypeId', 'LinkedToTypeId'])
            ->mapWithKeys(fn ($field) => [$field => $this->filled($field) ? $this->input($field) : null])
            ->all());

        $this->merge([
            'IsActive' => $this->boolean('IsActive', true),
            'AllowReplace' => $this->boolean('AllowReplace', true),
            'ExternalEntityType' => strtoupper(trim((string) $this->input('ExternalEntityType'))),
            'DocumentTypeIds' => collect($this->input('DocumentTypeIds', []))->filter(fn ($id) => $id !== '' && $id !== null)->values()->all(),
            'Modules' => $modules,
            'Properties' => $properties,
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // A list property with nothing to pick from would render an empty dropdown.
            foreach ($this->input('Properties', []) as $i => $property) {
                if (($property['DataType'] ?? null) === DataTypesEnum::List->value && empty($property['Options'])) {
                    $validator->errors()->add("Properties.$i.Options", 'A list property needs at least one option (one VALUE=Label per line).');
                }
            }

            // One default reviewer: a person or a team, not both.
            if ($this->filled('ReviewerUserId') && $this->filled('ReviewerTeamId')) {
                $validator->errors()->add('ReviewerTeamId', 'Pick a default reviewer or a default team, not both.');
            }

            // The source system's own service account cannot review what it filed.
            $serviceUserId = SourceSystem::query()->whereKey($this->integer('SourceSystemId'))->value('ServiceUserId');
            if ($serviceUserId && (int) $this->input('ReviewerUserId') === (int) $serviceUserId) {
                $validator->errors()->add('ReviewerUserId', 'The source system\'s service account cannot be its own reviewer.');
            }

            // Same rule the upload form applies: a document type that restricts its linked-to types can only be
            // offered by an event that names one of those (or none at all, when the type's own is then used).
            $linkedToTypeId = $this->integer('LinkedToTypeId');
            if ($linkedToTypeId) {
                DMSDocumentType::query()->with('linkedToTypes')->whereIn('Id', (array) $this->input('DocumentTypeIds', []))->get()
                    ->each(function (DMSDocumentType $type) use ($validator, $linkedToTypeId) {
                        $allowed = $type->linkedToTypes->pluck('Id');
                        if ($allowed->isNotEmpty() && ! $allowed->contains($linkedToTypeId)) {
                            $validator->errors()->add('LinkedToTypeId', "{$type->Name} cannot be linked to that type; pick another linked-to type or leave it empty.");
                        }
                    });
            }
        });
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function parseOptions(string $raw): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $raw) ?: [])
            ->map(fn ($line) => trim($line))
            ->filter()
            ->map(function ($line) {
                [$value, $label] = array_pad(explode('=', $line, 2), 2, null);

                return ['value' => trim($value), 'label' => trim($label ?? $value)];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string,string>
     */
    public function messages(): array
    {
        return [
            'Code.regex' => 'The code may only contain letters, digits, dot, dash or underscore.',
            'ExternalEntityType.regex' => 'The record type must be upper case letters, digits or underscore, e.g. ACCOUNT.',
            'Properties.*.Name.regex' => 'A property name may only contain letters, digits, dot, dash or underscore.',
            'Properties.*.Name.distinct' => 'Each property name must be unique within the event.',
            'Modules.*.distinct' => 'A module is listed more than once.',
        ];
    }
}
