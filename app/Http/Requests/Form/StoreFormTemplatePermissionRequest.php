<?php

namespace App\Http\Requests\Form;

use App\Enums\FormPermissionAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormTemplatePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization enforced in the controller (admin-only)
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $validActions = array_column(FormPermissionAction::cases(), 'value');

        return [
            'action' => ['required', Rule::in($validActions)],
            'permissible_type' => ['required', Rule::in(['role', 'department', 'team'])],
            'permissible_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $table = match ($this->permissible_type) {
                        'role' => 'roles',
                        'department' => 'departments',
                        'team' => 'teams',
                        default => null,
                    };
                    if ($table && ! \DB::table($table)->where('id', $value)->exists()) {
                        $fail("The selected {$this->permissible_type} does not exist.");
                    }
                },
            ],
        ];
    }
}
