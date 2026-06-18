<?php

use App\Enums\FormPermissionAction;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\FormTemplatePermission;
use App\Models\FormTemplateVersion;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

function makeAdmin(): array
{
    $role = Role::factory()->create(['name' => 'admin']);
    $admin = User::factory()->create(['role_id' => $role->id]);
    $token = Auth::guard('api')->tokenById($admin->id);

    return [$admin, $token];
}

function makeUser(): array
{
    $user = User::factory()->create();
    $token = Auth::guard('api')->tokenById($user->id);

    return [$user, $token];
}

function makeSubmissionWithVersion(User $user, FormTemplate $template): FormSubmission
{
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v = $submission->versions()->create([
        'user_id' => $user->id,
        'form_name' => $template->name,
        'content' => ['v' => 1],
        'version_number' => 1,
    ]);
    $submission->update(['current_version_id' => $v->id]);

    return $submission;
}

// ---------------------------------------------------------------------------
// Admin permission management — CRUD
// ---------------------------------------------------------------------------

test('admin can list permissions for a template', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}/permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.action', 'view')
        ->assertJsonPath('data.0.permissible_type', 'role')
        ->assertJsonPath('data.0.permissible_id', $role->id);
});

test('non-admin cannot list permissions', function () {
    [, $token] = makeUser();
    $template = FormTemplate::factory()->create();

    $this->getJson("/api/v1/form-templates/{$template->id}/permissions", [
        'Authorization' => "Bearer $token",
    ])->assertForbidden();
});

test('admin can grant a view permission to a role', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'view',
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ], ['Authorization' => "Bearer $token"])
        ->assertSuccessful()
        ->assertJsonPath('data.action', 'view')
        ->assertJsonPath('data.permissible_type', 'role')
        ->assertJsonPath('data.permissible_id', $role->id);

    $this->assertDatabaseHas('form_template_permissions', [
        'form_template_id' => $template->id,
        'action' => 'view',
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);
});

test('admin can grant a create permission to a department', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $department = Department::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'create',
        'permissible_type' => 'department',
        'permissible_id' => $department->id,
    ], ['Authorization' => "Bearer $token"])
        ->assertSuccessful()
        ->assertJsonPath('data.permissible_type', 'department');
});

test('admin can grant a create permission to a team', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $team = Team::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'create',
        'permissible_type' => 'team',
        'permissible_id' => $team->id,
    ], ['Authorization' => "Bearer $token"])
        ->assertSuccessful()
        ->assertJsonPath('data.permissible_type', 'team');
});

test('granting duplicate permission is idempotent', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $payload = [
        'action' => 'view',
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ];

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", $payload, ['Authorization' => "Bearer $token"])
        ->assertSuccessful();
    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", $payload, ['Authorization' => "Bearer $token"])
        ->assertSuccessful();

    $this->assertDatabaseCount('form_template_permissions', 1);
});

test('admin can revoke a permission', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $permission = FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->deleteJson("/api/v1/form-templates/{$template->id}/permissions/{$permission->id}", [], [
        'Authorization' => "Bearer $token",
    ])->assertNoContent();

    $this->assertDatabaseMissing('form_template_permissions', ['id' => $permission->id]);
});

test('cannot revoke a permission that belongs to a different template', function () {
    [$admin, $token] = makeAdmin();
    $template1 = FormTemplate::factory()->create();
    $template2 = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $permission = FormTemplatePermission::create([
        'form_template_id' => $template2->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->deleteJson("/api/v1/form-templates/{$template1->id}/permissions/{$permission->id}", [], [
        'Authorization' => "Bearer $token",
    ])->assertNotFound();
});

test('non-admin cannot grant permissions', function () {
    [, $token] = makeUser();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'view',
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ], ['Authorization' => "Bearer $token"])->assertForbidden();
});

test('grant permission validates required fields', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [], [
        'Authorization' => "Bearer $token",
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['action', 'permissible_type', 'permissible_id']);
});

test('grant permission rejects invalid action', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $role = Role::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'destroy',
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ], ['Authorization' => "Bearer $token"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['action']);
});

test('grant permission rejects nonexistent subject', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();

    $this->postJson("/api/v1/form-templates/{$template->id}/permissions", [
        'action' => 'view',
        'permissible_type' => 'role',
        'permissible_id' => 9999,
    ], ['Authorization' => "Bearer $token"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissible_id']);
});

// ---------------------------------------------------------------------------
// my-permissions endpoint
// ---------------------------------------------------------------------------

test('my-permissions returns all true when no grants exist (open by default)', function () {
    [, $token] = makeUser();
    $template = FormTemplate::factory()->create();

    $this->getJson("/api/v1/form-templates/{$template->id}/my-permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.view', true)
        ->assertJsonPath('data.permissions.create', true)
        ->assertJsonPath('data.permissions.edit', true);
});

test('my-permissions returns false only for restricted actions the user is not granted', function () {
    [$user, $token] = makeUser();
    $role = Role::factory()->create();
    $user->update(['role_id' => $role->id]);
    $template = FormTemplate::factory()->create();

    // Grant 'view' to this role
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);
    // Restrict 'create' to a different role (user is not in it)
    $otherRole = Role::factory()->create();
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}/my-permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.view', true)
        ->assertJsonPath('data.permissions.create', false)
        ->assertJsonPath('data.permissions.edit', true); // no edit grants → open default
});

test('admin always sees all permissions as true', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $otherRole = Role::factory()->create();

    foreach (FormPermissionAction::cases() as $action) {
        FormTemplatePermission::create([
            'form_template_id' => $template->id,
            'action' => $action->value,
            'permissible_type' => 'role',
            'permissible_id' => $otherRole->id,
        ]);
    }

    $this->getJson("/api/v1/form-templates/{$template->id}/my-permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.view', true)
        ->assertJsonPath('data.permissions.create', true)
        ->assertJsonPath('data.permissions.edit', true);
});

test('my-permissions returns true when user is granted via department', function () {
    [$user, $token] = makeUser();
    $department = Department::factory()->create();
    $user->departments()->attach($department);

    $template = FormTemplate::factory()->create();
    $otherRole = Role::factory()->create();

    // Lock down 'create' to a role, but also grant user's department
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'department',
        'permissible_id' => $department->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}/my-permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.create', true);
});

test('my-permissions returns true when user is granted via team', function () {
    [$user, $token] = makeUser();
    $team = Team::factory()->create();
    $user->teams()->attach($team);

    $template = FormTemplate::factory()->create();
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'team',
        'permissible_id' => $team->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}/my-permissions", [
        'Authorization' => "Bearer $token",
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.permissions.create', true);
});

// ---------------------------------------------------------------------------
// ABAC on FormTemplate show (view action)
// ---------------------------------------------------------------------------

test('user can view template when no view grants exist (open by default)', function () {
    [, $token] = makeUser();
    $template = FormTemplate::factory()->create(['is_active' => true]);

    $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $token",
    ])->assertSuccessful();
});

test('user cannot view template when view grants exist and user is not included', function () {
    [$user, $token] = makeUser();
    $template = FormTemplate::factory()->create(['is_active' => true]);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $token",
    ])->assertForbidden();
});

test('user can view template when their role is granted view', function () {
    [$user, $token] = makeUser();
    $role = Role::factory()->create();
    $user->update(['role_id' => $role->id]);
    $template = FormTemplate::factory()->create(['is_active' => true]);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);
    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $token",
    ])->assertSuccessful();
});

test('admin can view template regardless of view grants', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create(['is_active' => true]);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::View->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $token",
    ])->assertSuccessful();
});

// ---------------------------------------------------------------------------
// ABAC on FormSubmission store (create action)
// ---------------------------------------------------------------------------

test('user can create submission when no create grants exist (open by default)', function () {
    [, $token] = makeUser();
    $template = FormTemplate::factory()->create();
    $version = FormTemplateVersion::factory()->create(['template_id' => $template->id]);

    $this->postJson('/api/v1/form-submissions', [
        'form_template_id' => $template->id,
        'form_template_version_id' => $version->id,
        'form_name' => 'Test',
        'content' => ['field' => 'value'],
    ], ['Authorization' => "Bearer $token"])->assertCreated();
});

test('user cannot create submission when create grants exist and user is not included', function () {
    [$user, $token] = makeUser();
    $template = FormTemplate::factory()->create();
    $version = FormTemplateVersion::factory()->create(['template_id' => $template->id]);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->postJson('/api/v1/form-submissions', [
        'form_template_id' => $template->id,
        'form_template_version_id' => $version->id,
        'form_name' => 'Test',
        'content' => ['field' => 'value'],
    ], ['Authorization' => "Bearer $token"])->assertForbidden();
});

test('user can create submission when their role is granted create', function () {
    [$user, $token] = makeUser();
    $role = Role::factory()->create();
    $user->update(['role_id' => $role->id]);
    $template = FormTemplate::factory()->create();
    $version = FormTemplateVersion::factory()->create(['template_id' => $template->id]);

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->postJson('/api/v1/form-submissions', [
        'form_template_id' => $template->id,
        'form_template_version_id' => $version->id,
        'form_name' => 'Test',
        'content' => ['field' => 'value'],
    ], ['Authorization' => "Bearer $token"])->assertCreated();
});

test('admin can create submission regardless of create grants', function () {
    [$admin, $token] = makeAdmin();
    $template = FormTemplate::factory()->create();
    $version = FormTemplateVersion::factory()->create(['template_id' => $template->id]);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Create->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->postJson('/api/v1/form-submissions', [
        'form_template_id' => $template->id,
        'form_template_version_id' => $version->id,
        'form_name' => 'Test',
        'content' => ['field' => 'value'],
    ], ['Authorization' => "Bearer $token"])->assertCreated();
});

// ---------------------------------------------------------------------------
// ABAC on FormSubmission update (edit action)
// ---------------------------------------------------------------------------

test('user can update submission when no edit grants exist (open by default)', function () {
    [$user, $token] = makeUser();
    $template = FormTemplate::factory()->create();
    $submission = makeSubmissionWithVersion($user, $template);

    $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => 'Updated',
        'content' => ['v' => 2],
        'version_number' => 1,
    ], ['Authorization' => "Bearer $token"])->assertSuccessful();
});

test('user cannot update submission when edit grants exist and user is not included', function () {
    [$user, $token] = makeUser();
    $template = FormTemplate::factory()->create();
    $submission = makeSubmissionWithVersion($user, $template);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Edit->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => 'Updated',
        'content' => ['v' => 2],
        'version_number' => 1,
    ], ['Authorization' => "Bearer $token"])->assertForbidden();
});

test('user can update submission when their role is granted edit', function () {
    [$user, $token] = makeUser();
    $role = Role::factory()->create();
    $user->update(['role_id' => $role->id]);
    $template = FormTemplate::factory()->create();
    $submission = makeSubmissionWithVersion($user, $template);

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Edit->value,
        'permissible_type' => 'role',
        'permissible_id' => $role->id,
    ]);

    $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => 'Updated',
        'content' => ['v' => 2],
        'version_number' => 1,
    ], ['Authorization' => "Bearer $token"])->assertSuccessful();
});

test('admin can update submission regardless of edit grants', function () {
    [$admin, $adminToken] = makeAdmin();
    [$user] = makeUser();
    $template = FormTemplate::factory()->create();
    $submission = makeSubmissionWithVersion($user, $template);
    $otherRole = Role::factory()->create();

    FormTemplatePermission::create([
        'form_template_id' => $template->id,
        'action' => FormPermissionAction::Edit->value,
        'permissible_type' => 'role',
        'permissible_id' => $otherRole->id,
    ]);

    $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => 'Updated',
        'content' => ['v' => 2],
        'version_number' => 1,
    ], ['Authorization' => "Bearer $adminToken"])->assertSuccessful();
});

// ---------------------------------------------------------------------------
// Unauthenticated access
// ---------------------------------------------------------------------------

test('unauthenticated user cannot access permission routes', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'list permissions' => ['GET', '/api/v1/form-templates/1/permissions'],
    'grant permission' => ['POST', '/api/v1/form-templates/1/permissions'],
    'revoke permission' => ['DELETE', '/api/v1/form-templates/1/permissions/1'],
    'my-permissions' => ['GET', '/api/v1/form-templates/1/my-permissions'],
]);
