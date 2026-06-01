<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\User;

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

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, FormSubmission $formSubmission): bool
    {
        return true;
    }

    public function delete(User $user, FormSubmission $formSubmission): bool
    {
        return $user->isAdmin();
    }
}
