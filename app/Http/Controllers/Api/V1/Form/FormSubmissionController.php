<?php

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Controllers\Controller;
use App\Http\Requests\Form\StoreFormSubmissionRequest;
use App\Http\Requests\Form\UpdateFormSubmissionRequest;
use App\Http\Resources\Form\FormSubmissionResource;
use App\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FormSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FormSubmission::query()
            ->with(['template', 'currentVersion.user']);

        if ($request->filled('form_template_id')) {
            $query->where('form_template_id', $request->form_template_id);
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        return FormSubmissionResource::collection($query->paginate($perPage));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormSubmissionRequest $request): FormSubmissionResource
    {
        return DB::transaction(function () use ($request) {
            $submission = FormSubmission::create([
                'form_template_id' => $request->form_template_id,
            ]);
            $submission->load('template');

            $version = $submission->versions()->create([
                'user_id' => Auth::guard('api')->id(),
                'form_name' => $request->form_name,
                'content' => $request->content,
                'version_number' => 1,
            ]);

            $submission->update(['current_version_id' => $version->id]);

            return new FormSubmissionResource($submission->load(['template', 'currentVersion.user']));
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(FormSubmission $formSubmission): FormSubmissionResource
    {
        return new FormSubmissionResource($formSubmission->load(['template', 'currentVersion.user', 'versions']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormSubmissionRequest $request, FormSubmission $formSubmission): FormSubmissionResource
    {
        return DB::transaction(function () use ($request, $formSubmission) {
            $lockedSubmission = FormSubmission::with('currentVersion')
                ->lockForUpdate()
                ->findOrFail($formSubmission->id);
            $currentVersion = $lockedSubmission->currentVersion;

            if ($currentVersion->version_number !== (int) $request->version_number) {
                abort(409, 'Version conflict');
            }

            $newVersion = $lockedSubmission->versions()->create([
                'user_id' => Auth::guard('api')->id(),
                'form_name' => $request->form_name,
                'content' => $request->content,
                'version_number' => $currentVersion->version_number + 1,
            ]);

            $lockedSubmission->update(['current_version_id' => $newVersion->id]);

            return new FormSubmissionResource($lockedSubmission->load(['template', 'currentVersion.user']));
        });
    }
}
