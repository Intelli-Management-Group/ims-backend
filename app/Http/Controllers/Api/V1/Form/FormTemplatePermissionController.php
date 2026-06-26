<?php

namespace App\Http\Controllers\Api\V1\Form;

use App\Http\Controllers\Controller;
use App\Http\Requests\Form\StoreFormTemplatePermissionRequest;
use App\Http\Resources\Form\FormTemplatePermissionResource;
use App\Models\FormTemplate;
use App\Models\FormTemplatePermission;
use App\Services\Form\FormPermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FormTemplatePermissionController extends Controller
{
    /**
     * List all permission grants for a template.
     * Admin only.
     */
    public function index(Request $request, FormTemplate $formTemplate): AnonymousResourceCollection
    {
        abort_if(! $request->user()->isAdmin(), 403);

        return FormTemplatePermissionResource::collection(
            $formTemplate->permissions()->get()
        );
    }

    /**
     * Grant a permission on a template to a role, department, or team.
     * Admin only. Duplicate grants are silently ignored (idempotent).
     */
    public function store(StoreFormTemplatePermissionRequest $request, FormTemplate $formTemplate): FormTemplatePermissionResource
    {
        abort_if(! $request->user()->isAdmin(), 403);

        $permission = $formTemplate->permissions()->firstOrCreate([
            'action' => $request->action,
            'permissible_type' => $request->permissible_type,
            'permissible_id' => $request->permissible_id,
        ]);

        return new FormTemplatePermissionResource($permission);
    }

    /**
     * Revoke a permission grant.
     * Admin only.
     */
    public function destroy(Request $request, FormTemplate $formTemplate, FormTemplatePermission $permission): JsonResponse
    {
        abort_if(! $request->user()->isAdmin(), 403);
        abort_if($permission->form_template_id !== $formTemplate->id, 404);

        $permission->delete();

        return response()->json(null, 204);
    }

    /**
     * Return the current user's resolved permissions for a template.
     * Available to all authenticated users.
     *
     * @return array<string, mixed>
     */
    public function myPermissions(Request $request, FormTemplate $formTemplate): JsonResponse
    {
        $permissions = app(FormPermissionService::class)
            ->resolvedPermissions($request->user(), $formTemplate);

        return response()->json([
            'data' => [
                'form_template_id' => $formTemplate->id,
                'permissions' => $permissions,
            ],
        ]);
    }
}
