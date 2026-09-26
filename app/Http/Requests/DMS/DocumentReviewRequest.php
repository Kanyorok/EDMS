<?php

namespace App\Http\Requests\DMS;

use App\Models\Auth\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentReviewRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ReviewerId' => ['required', 'string'],
            'RequestNote' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function getReviewer(): User
    {
        $reviewer = User::query()->where('UserID', Str::upper($this->string('ReviewerId')))->first();
        if (! $reviewer instanceof User) {
            throw ValidationException::withMessages(['ReviewerId' => 'invalid reviewer selected.']);
        }

        if ($reviewer->Id === $this->user()->Id) {
            throw ValidationException::withMessages(['ReviewerId' => 'you cannot request a review from yourself.']);
        }

        return $reviewer;
    }
}
