<?php

namespace App\Services\Form;

use App\Enums\FormPermissionAction;
use App\Models\Department;
use App\Models\FormTemplate;
use App\Models\FormTemplatePermission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;

class FormPermissionService
{
    /**
     * Determine whether a user may perform an action on a template.
     *
     * Open-by-default: when no permission records exist for this template+action,
     * all authenticated users are allowed. Restrictions only apply once at least
     * one grant row is present — at which point only subjects with a matching
     * grant are allowed.
     *
     * Admins bypass all checks.
     */
    public function userCanOnTemplate(User $user, FormPermissionAction $action, FormTemplate $template): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $hasAnyGrants = FormTemplatePermission::where('form_template_id', $template->id)
            ->where('action', $action->value)
            ->exists();

        // Open by default: no grants defined means everyone is allowed.
        if (! $hasAnyGrants) {
            return true;
        }

        return FormTemplatePermission::where('form_template_id', $template->id)
            ->where('action', $action->value)
            ->where(function ($q) use ($user): void {
                $q->where(function ($q) use ($user): void {
                    $q->where('permissible_type', (new Role)->getMorphClass())
                        ->where('permissible_id', $user->role_id);
                })->orWhere(function ($q) use ($user): void {
                    $q->where('permissible_type', (new Department)->getMorphClass())
                        ->whereIn('permissible_id', $user->departments->pluck('id'));
                })->orWhere(function ($q) use ($user): void {
                    $q->where('permissible_type', (new Team)->getMorphClass())
                        ->whereIn('permissible_id', $user->teams->pluck('id'));
                });
            })
            ->exists();
    }

    /**
     * Resolve all form-level actions for a user on a template in one pass.
     *
     * @return array<string, bool>
     */
    public function resolvedPermissions(User $user, FormTemplate $template): array
    {
        $resolved = [];
        foreach (FormPermissionAction::cases() as $action) {
            $resolved[$action->value] = $this->userCanOnTemplate($user, $action, $template);
        }

        return $resolved;
    }
}
