<?php

namespace App\Http\Requests\Form;

use App\Models\FormTemplate;
use App\Rules\ContentMatchesTemplateSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFormSubmissionRequest extends FormRequest
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
        $template = FormTemplate::find($this->form_template_id);

        $contentRules = ['required', 'array'];

        if ($template) {
            $contentRules[] = new ContentMatchesTemplateSchema($template->json_schema);
        }

        return [
            'form_template_id' => ['required', 'exists:form_templates,id'],
            'form_name' => ['required', 'string', 'max:255'],
            'content' => $contentRules,
        ];
    }
}
