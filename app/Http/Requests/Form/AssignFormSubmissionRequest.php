<?php

namespace App\Http\Requests\Form;

use App\Enums\AssigneeScope;
use App\Models\Department;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssignFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignee_type' => ['nullable', Rule::in(['user', 'team', 'department'])],
            'assignee_id' => ['nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('assignee_type');
            $id = $this->input('assignee_id');

            if (($type === null) !== ($id === null)) {
                $validator->errors()->add('assignee_type', 'assignee_type and assignee_id must both be provided or both be null.');

                return;
            }

            if ($type !== null && $id !== null) {
                $exists = match ($type) {
                    'user' => User::query()->where('id', $id)->exists(),
                    'team' => Team::query()->where('id', $id)->exists(),
                    'department' => Department::query()->where('id', $id)->exists(),
                    default => false,
                };

                if (! $exists) {
                    $validator->errors()->add('assignee_id', "The selected {$type} does not exist.");

                    return;
                }

                $submission = $this->route('formSubmission');
                $scope = $submission?->template?->assignee_scope;

                if ($scope !== null && $scope !== AssigneeScope::Global) {
                    if ($scope->value !== $type) {
                        $validator->errors()->add('assignee_type', "This template only allows assignments of type '{$scope->value}'.");
                    }
                }
            }
        });
    }
}
