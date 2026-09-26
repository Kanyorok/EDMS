<?php

namespace App\Http\Requests\DMS;

use App\Models\Auth\User;
use App\Models\DMS\DMSSignature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ApproveDocumentValidationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    /**
     * Signature is only collected at the stage that actually signs the document - the last
     * stage of the type's workflow, or the single step when no workflow is bound. Intermediate
     * stages approve with remarks only, so the field is not sent and must not be required.
     */
    public function rules(): array
    {
        return [
            'Signature' => ['nullable', 'string', 'max:200'],
            'Remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getSignature(User $user): DMSSignature
    {
        $sign = DMSSignature::query()->user($user)->where('SignatureId', $this->string('Signature'))->first();
        if (! $sign instanceof DMSSignature) {
            throw ValidationException::withMessages(['Signature' => 'Invalid signature provided']);
        }

        return $sign;
    }

    public function getRemarks(string $default = 'Approved'): string
    {
        $remarks = $this->string('Remarks')->trim()->toString();

        return $remarks !== '' ? $remarks : $default;
    }
}
