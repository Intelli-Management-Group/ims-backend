<?php

use App\Http\Controllers\Api\V1\Form\FormTemplateController;
use App\Http\Controllers\Api\V1\Form\FormTemplateVersionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->group(function () {
    Route::apiResource('form-templates', FormTemplateController::class)->except(['destroy']);

    Route::get('form-templates/{formTemplate}/versions', [FormTemplateVersionController::class, 'index']);
    Route::get('form-templates/{formTemplate}/versions/{version}', [FormTemplateVersionController::class, 'show']);
});
