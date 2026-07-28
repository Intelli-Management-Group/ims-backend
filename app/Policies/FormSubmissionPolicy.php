<?php

namespace App\Policies;

use App\Enums\FormPermissionAction;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use App\Services\Form\FormPermissionService;

class FormSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FormSubmission $formSubmission): bool
    {
        return true;
    }

    /**
     * @param  FormTemplate  $template  The template the submission will be created against.
     */
    public function create(User $user, FormTemplate $template): bool
    {
        return app(FormPermissionService::class)
            ->userCanOnTemplate($user, FormPermissionAction::Create, $template);
    }

    public function update(User $user, FormSubmission $formSubmission): bool
    {
        return app(FormPermissionService::class)
            ->userCanOnTemplate($user, FormPermissionAction::Edit, $formSubmission->template);
    }

    public function delete(User $user, FormSubmission $formSubmission): bool
    {
        return $user->isAdmin();
    }
}
