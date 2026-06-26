<?php

use App\Http\Controllers\Api\V1\Form\FormTemplatePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    // Admin CRUD for managing permission grants on a template
    Route::get('form-templates/{formTemplate}/permissions', [FormTemplatePermissionController::class, 'index']);
    Route::post('form-templates/{formTemplate}/permissions', [FormTemplatePermissionController::class, 'store']);
    Route::delete('form-templates/{formTemplate}/permissions/{permission}', [FormTemplatePermissionController::class, 'destroy']);

    // Resolved permissions for the authenticated user on a specific template
    Route::get('form-templates/{formTemplate}/my-permissions', [FormTemplatePermissionController::class, 'myPermissions']);
});
