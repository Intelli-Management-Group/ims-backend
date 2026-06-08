<?php

namespace App\Http\Requests\Form;

use App\Rules\ContentMatchesTemplateSchema;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFormSubmissionRequest extends FormRequest
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
        $template = $this->route('form_submission')?->template;

        $contentRules = ['required', 'array'];

        if ($template) {
            $contentRules[] = new ContentMatchesTemplateSchema($template->json_schema);
        }

        return [
            'form_name' => ['required', 'string', 'max:255'],
            'content' => $contentRules,
            'version_number' => ['required', 'integer'],
        ];
    }
}
