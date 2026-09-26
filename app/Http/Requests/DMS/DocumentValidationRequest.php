<?php

namespace App\Http\Requests\DMS;

use App\Enums\DMS\DocumentValidationModeEnum;
use App\Models\DMS\DocumentValidation;
use App\Models\DMS\DocumentValidationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class DocumentValidationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // ValidationType is only required when a type is actually needed.
        // ApprovalOnly mode routes the document through a generic approval workflow
        // without binding it to a validation type.
        $typeRequired = $this->getSubmissionMode() !== DocumentValidationModeEnum::ApprovalOnly;

        return [
            'ValidationType' => $typeRequired
                ? ['required', 'string', 'max:200']
                : ['nullable', 'string', 'max:200'],
            'SubmissionMode' => ['required', 'string', 'in:va,ap,av'],
        ];
    }

    /**
     * Resolve the submission mode from the request, falling back to the default (Validation).
     */
    public function getSubmissionMode(): DocumentValidationModeEnum
    {
        return DocumentValidationModeEnum::tryFrom($this->string('SubmissionMode')->toString())
            ?? DocumentValidationModeEnum::Validation;
    }

    /**
     * Resolve the validation type from the request.
     * Returns null for ApprovalOnly mode when no type was submitted.
     *
     * @throws \Illuminate\Validation\ValidationException  when a type is required but invalid.
     */
    public function getValidationType(): ?DocumentValidationType
    {
        $raw = $this->string('ValidationType')->toString();

        if ($raw === '') {
            // No type submitted – only allowed for ApprovalOnly.
            return null;
        }

        $ValidationType = DocumentValidationType::query()
            ->where('ValidationTypeId', $raw)
            ->first();

        if ($ValidationType instanceof DocumentValidationType) {
            return $ValidationType;
        }

        throw ValidationException::withMessages(['ValidationType' => 'invalid validation type']);
    }

    /**
     * Next free ValidationId.
     *
     * When no type is bound (ApprovalOnly without a type), the prefix is "AP" and the
     * counter runs against the full t_DocumentValidations table to avoid collisions.
     */
    public function createValidationId(?DocumentValidationType $type): string
    {
        if ($type === null) {
            // Approval-only without a type: use a generic "AP" prefix.
            $id = DocumentValidation::query()->withTrashed()
                ->where('ValidationId', 'like', 'AP-%')
                ->count();
            do {
                $id++;
                $validationId = 'AP-' . $id;
            } while (DocumentValidation::query()->withTrashed()->where('ValidationId', $validationId)->exists());

            return $validationId;
        }

        $id = $type->validations()->withTrashed()->count();
        do {
            $id++;
            $validationId = $type->ValidationTypeId . '-' . $id;
        } while (DocumentValidation::query()->withTrashed()->where('ValidationId', $validationId)->exists());

        return $validationId;
    }
}
