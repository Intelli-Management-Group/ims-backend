<?php

namespace App\Http\Requests\Form;

use App\Enums\AssigneeScope;
use App\Rules\ValidJsonSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateFormTemplateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('form_templates')->ignore($this->route('form_template'))],
            'json_schema' => ['sometimes', 'array', new ValidJsonSchema],
            'ui_schema' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'assignee_scope' => ['nullable', new Enum(AssigneeScope::class)],
            'version_number' => ['required', 'integer'],
        ];
    }
}
