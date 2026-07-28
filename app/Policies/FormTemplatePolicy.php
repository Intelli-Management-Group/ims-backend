<?php

namespace App\Policies;

use App\Enums\FormPermissionAction;
use App\Models\FormTemplate;
use App\Models\User;
use App\Services\Form\FormPermissionService;

class FormTemplatePolicy
{
    public function view(User $user, FormTemplate $formTemplate): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $formTemplate->is_active) {
            return false;
        }

        return app(FormPermissionService::class)
            ->userCanOnTemplate($user, FormPermissionAction::View, $formTemplate);
    }

    public function viewInactive(User $user): bool
    {
        return $user->isAdmin();
    }
}
