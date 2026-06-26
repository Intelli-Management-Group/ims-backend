<?php

use App\Http\Controllers\Api\V1\Form\AssignmentController;
use App\Http\Controllers\Api\V1\Form\FormSubmissionController;
use App\Http\Controllers\Api\V1\Form\FormSubmissionVersionController;
use App\Http\Controllers\Api\V1\Form\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::apiResource('form-submissions', FormSubmissionController::class)->except(['destroy']);

    Route::post('form-submissions/{formSubmission}/submit', [WorkflowController::class, 'submit']);
    Route::post('form-submissions/{formSubmission}/approve', [WorkflowController::class, 'approve']);
    Route::post('form-submissions/{formSubmission}/reject', [WorkflowController::class, 'reject']);

    Route::post('form-submissions/{formSubmission}/assign', [AssignmentController::class, 'assign']);

    Route::get('form-submissions/{formSubmission}/versions', [FormSubmissionVersionController::class, 'index']);
    Route::get('form-submissions/{formSubmission}/versions/{version}', [FormSubmissionVersionController::class, 'show']);
});
