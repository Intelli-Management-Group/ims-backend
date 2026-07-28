<?php

namespace App\Http\Requests\Form;

use App\Rules\ValidJsonSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormTemplateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('form_templates')],
            'json_schema' => ['present', 'array', new ValidJsonSchema],
            'ui_schema' => ['present', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
