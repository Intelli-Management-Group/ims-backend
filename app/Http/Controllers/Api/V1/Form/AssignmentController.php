<?php

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Controllers\Controller;
use App\Http\Requests\Form\AssignFormSubmissionRequest;
use App\Http\Resources\Form\FormSubmissionResource;
use App\Models\FormSubmission;

class AssignmentController extends Controller
{
    /**
     * Assign (or unassign) a form submission to a user, team, or department.
     */
    public function assign(AssignFormSubmissionRequest $request, FormSubmission $formSubmission): FormSubmissionResource
    {
        $this->authorize('assign', $formSubmission);

        $formSubmission->update([
            'assignee_type' => $request->input('assignee_type'),
            'assignee_id' => $request->input('assignee_id'),
        ]);

        return new FormSubmissionResource($formSubmission->fresh()->load(['template', 'creator', 'currentVersion', 'assignee']));
    }
}
