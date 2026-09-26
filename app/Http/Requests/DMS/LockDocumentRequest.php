<?php

namespace App\Http\Requests\DMS;

use App\Models\DMS\Document;
use App\Models\DMS\LegalHold;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class LockDocumentRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'DocumentId' => ['required', 'string', 'exists:t_Documents,DocumentId'],
            'Description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function getDocument(): Document
    {
        return Document::where('DocumentId', $this->string('DocumentId')->toString())->firstOrFail();
    }

    public function getRef(): string
    {
        $number = LegalHold::withTrashed()->count();
        do {
            $number++;
            $ref = Str::slug('Hold' . Str::padLeft(($number), 4, '0'));
        } while (LegalHold::where('Ref', $ref)->withTrashed()->exists());

        return $ref;
    }
}
