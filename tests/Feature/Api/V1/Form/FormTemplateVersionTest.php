<?php

use App\Models\FormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = Auth::guard('api')->tokenById($this->user->id);
});

// ---------------------------------------------------------------------------
// Helper to create a template with an initial version (mirrors store flow)
// ---------------------------------------------------------------------------
function templateWithVersion(array $attrs = []): FormTemplate
{
    $template = FormTemplate::factory()->create($attrs);

    $version = $template->versions()->create([
        'user_id' => null,
        'name' => $template->name,
        'json_schema' => $template->json_schema,
        'ui_schema' => $template->ui_schema,
        'is_active' => $template->is_active,
        'version_number' => 1,
    ]);

    $template->update(['current_version_id' => $version->id]);

    return $template->fresh();
}

// ---------------------------------------------------------------------------
// Versions index
// ---------------------------------------------------------------------------

test('user can list versions for a template', function () {
    $template = FormTemplate::factory()->create();
    $template->versions()->create(['user_id' => $this->user->id, 'name' => $template->name, 'json_schema' => [], 'ui_schema' => [], 'is_active' => true, 'version_number' => 1]);
    $template->versions()->create(['user_id' => $this->user->id, 'name' => $template->name, 'json_schema' => [], 'ui_schema' => [], 'is_active' => true, 'version_number' => 2]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('versions list is ordered by version_number descending', function () {
    $template = FormTemplate::factory()->create();
    $template->versions()->create(['user_id' => $this->user->id, 'name' => 'v1', 'json_schema' => [], 'ui_schema' => [], 'is_active' => true, 'version_number' => 1]);
    $template->versions()->create(['user_id' => $this->user->id, 'name' => 'v2', 'json_schema' => [], 'ui_schema' => [], 'is_active' => true, 'version_number' => 2]);
    $template->versions()->create(['user_id' => $this->user->id, 'name' => 'v3', 'json_schema' => [], 'ui_schema' => [], 'is_active' => true, 'version_number' => 3]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.version_number', 3)
        ->assertJsonPath('data.1.version_number', 2)
        ->assertJsonPath('data.2.version_number', 1);
});

test('versions list returns empty array when template has no versions', function () {
    $template = FormTemplate::factory()->create();

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(0, 'data');
});

test('versions list for non-existent template returns 404', function () {
    $response = $this->getJson('/api/v1/form-templates/999/versions', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

// ---------------------------------------------------------------------------
// Versions show
// ---------------------------------------------------------------------------

test('user can show a specific version of a template', function () {
    $template = FormTemplate::factory()->create(['name' => 'My Form']);
    $version = $template->versions()->create([
        'user_id' => $this->user->id,
        'name' => 'My Form',
        'json_schema' => ['type' => 'object'],
        'ui_schema' => [],
        'is_active' => true,
        'version_number' => 1,
    ]);
    $template->update(['current_version_id' => $version->id]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions/{$version->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $version->id)
        ->assertJsonPath('data.template_id', $template->id)
        ->assertJsonPath('data.version_number', 1)
        ->assertJsonPath('data.name', 'My Form')
        ->assertJsonPath('data.json_schema.type', 'object')
        ->assertJsonPath('data.user_id', $this->user->id);
});

test('show version returns 404 if version does not belong to the template', function () {
    $template1 = FormTemplate::factory()->create();
    $template2 = FormTemplate::factory()->create();

    $version = $template1->versions()->create([
        'user_id' => $this->user->id,
        'name' => $template1->name,
        'json_schema' => [],
        'ui_schema' => [],
        'is_active' => true,
        'version_number' => 1,
    ]);

    $response = $this->getJson("/api/v1/form-templates/{$template2->id}/versions/{$version->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

test('show non-existent version returns 404', function () {
    $template = FormTemplate::factory()->create();

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions/999", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

// ---------------------------------------------------------------------------
// Version resource shape
// ---------------------------------------------------------------------------

test('version resource includes user relationship', function () {
    $template = FormTemplate::factory()->create();
    $version = $template->versions()->create([
        'user_id' => $this->user->id,
        'name' => $template->name,
        'json_schema' => [],
        'ui_schema' => [],
        'is_active' => true,
        'version_number' => 1,
    ]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions/{$version->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.user.id', $this->user->id)
        ->assertJsonPath('data.user.name', $this->user->name);
});

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

test('unauthenticated user cannot access template version routes', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'versions_index' => ['GET', '/api/v1/form-templates/1/versions'],
    'versions_show' => ['GET', '/api/v1/form-templates/1/versions/1'],
]);

// ---------------------------------------------------------------------------
// Store creates version 1
// ---------------------------------------------------------------------------

test('creating a template via API creates version 1', function () {
    $response = $this->postJson('/api/v1/form-templates', [
        'name' => 'New Form',
        'json_schema' => ['type' => 'object'],
        'ui_schema' => [],
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertCreated();

    $templateId = $response->json('data.id');

    $this->assertDatabaseCount('form_template_versions', 1);
    $this->assertDatabaseHas('form_template_versions', [
        'template_id' => $templateId,
        'version_number' => 1,
        'name' => 'New Form',
        'user_id' => $this->user->id,
    ]);
});

test('store response includes current_version', function () {
    $response = $this->postJson('/api/v1/form-templates', [
        'name' => 'New Form',
        'json_schema' => ['type' => 'object'],
        'ui_schema' => [],
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.current_version.version_number', 1)
        ->assertJsonPath('data.current_version.name', 'New Form')
        ->assertJsonPath('data.current_version.json_schema.type', 'object');
});

// ---------------------------------------------------------------------------
// Update creates new version
// ---------------------------------------------------------------------------

test('updating a template creates a new version', function () {
    $template = templateWithVersion();

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Updated Name',
        'json_schema' => ['type' => 'object', 'title' => 'Updated'],
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.current_version.version_number', 2)
        ->assertJsonPath('data.current_version.name', 'Updated Name')
        ->assertJsonPath('data.current_version.json_schema.title', 'Updated');

    $this->assertDatabaseCount('form_template_versions', 2);
});

test('update snapshots full template state into version', function () {
    $template = templateWithVersion([
        'name' => 'Original',
        'json_schema' => ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]],
        'ui_schema' => ['ui:order' => ['a']],
        'is_active' => true,
    ]);

    $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Changed',
        'json_schema' => ['type' => 'object', 'properties' => ['b' => ['type' => 'integer']]],
        'ui_schema' => ['ui:order' => ['b']],
        'is_active' => false,
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $this->assertDatabaseHas('form_template_versions', [
        'template_id' => $template->id,
        'version_number' => 2,
        'name' => 'Changed',
        'is_active' => false,
    ]);
});

test('update returns 409 on version conflict', function () {
    $template = templateWithVersion();

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Should Fail',
        'version_number' => 99,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertStatus(409);
    $this->assertDatabaseCount('form_template_versions', 1);
});

test('update rejects stale version_number against current db state', function () {
    $template = templateWithVersion();

    // Simulate a concurrent write advancing the version
    $v2 = $template->versions()->create([
        'user_id' => $this->user->id,
        'name' => 'Concurrent Change',
        'json_schema' => $template->json_schema,
        'ui_schema' => $template->ui_schema,
        'is_active' => $template->is_active,
        'version_number' => 2,
    ]);
    $template->update(['current_version_id' => $v2->id]);

    // Our request still believes it is on version 1 — should 409
    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Should Fail',
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertStatus(409);
    $this->assertDatabaseCount('form_template_versions', 2);
});

test('update requires version_number', function () {
    $template = templateWithVersion();

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'No version number',
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['version_number']);
});

test('update version_number must be an integer', function () {
    $template = templateWithVersion();

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Bad version',
        'version_number' => 'one',
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['version_number']);
});

// ---------------------------------------------------------------------------
// Admin can see inactive template versions
// ---------------------------------------------------------------------------

test('admin can list versions of an inactive template', function () {
    $admin = User::factory()->create();
    $role = Role::factory()->create(['name' => 'admin', 'is_active' => true]);
    $admin->update(['role_id' => $role->id]);
    $adminToken = Auth::guard('api')->tokenById($admin->id);

    $template = templateWithVersion(['is_active' => false]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}/versions", [
        'Authorization' => "Bearer $adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});
