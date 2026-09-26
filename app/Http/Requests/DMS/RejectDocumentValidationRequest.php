<?php

namespace App\Http\Requests\DMS;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentValidationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'Reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'Reason.required' => 'A reason is required so the document can be corrected and re-submitted.',
        ];
    }

    public function getReason(): string
    {
        return $this->string('Reason')->trim()->toString();
    }
}
