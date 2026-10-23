<?php

namespace App\Http\Requests\Form;

use Illuminate\Foundation\Http\FormRequest;

class RejectFormSubmissionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string'],
        ];
    }
}
