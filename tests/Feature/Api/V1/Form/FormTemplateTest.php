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

test('user can list form templates', function () {
    FormTemplate::factory(3)->create(['is_active' => true]);
    FormTemplate::factory(2)->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/form-templates', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('admin user can see inactive form templates in list', function () {
    FormTemplate::factory(3)->create(['is_active' => true]);
    FormTemplate::factory(2)->create(['is_active' => false]);

    $admin = User::factory()->create();
    $role = Role::factory()->create(['name' => 'admin', 'is_active' => true]);
    $admin->update(['role_id' => $role->id]);

    $adminToken = Auth::guard('api')->tokenById($admin->id);

    $response = $this->getJson('/api/v1/form-templates', [
        'Authorization' => "Bearer $adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data');
});

test('form template search filter returns matching templates only', function () {
    FormTemplate::factory()->create(['name' => 'Registration Form']);
    FormTemplate::factory()->create(['name' => 'Survey Form']);

    $response = $this->getJson('/api/v1/form-templates?search=Registration', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Registration Form');
});

test('user can show form template', function () {
    $template = FormTemplate::factory()->create();

    $response = $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $template->id)
        ->assertJsonPath('data.name', $template->name)
        ->assertJsonPath('data.json_schema', $template->json_schema)
        ->assertJsonPath('data.ui_schema', $template->ui_schema);
});

test('user can create form template', function () {
    $data = [
        'name' => 'New Form',
        'json_schema' => ['type' => 'object'],
        'ui_schema' => [],
    ];

    $response = $this->postJson('/api/v1/form-templates', $data, [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'New Form')
        ->assertJsonPath('data.json_schema.type', 'object')
        ->assertJsonPath('data.ui_schema', [])
        ->assertJsonPath('data.created_by', $this->user->id);
});

test('user can update form template', function () {
    $template = FormTemplate::factory()->create();

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Updated Name',
        'json_schema' => ['type' => 'object', 'title' => 'Updated'],
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.json_schema.title', 'Updated')
        ->assertJsonPath('data.is_active', false);
});

test('form template validation', function (array $data, array $errors) {
    $response = $this->postJson('/api/v1/form-templates', $data, [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [['json_schema' => [], 'ui_schema' => []], ['name']],
    'missing json_schema' => [['name' => 'Test', 'ui_schema' => []], ['json_schema']],
    'missing ui_schema' => [['name' => 'Test', 'json_schema' => []], ['ui_schema']],
    'invalid json_schema type' => [['name' => 'Test', 'json_schema' => 'not-an-array', 'ui_schema' => []], ['json_schema']],
    'invalid ui_schema type' => [['name' => 'Test', 'json_schema' => [], 'ui_schema' => 'not-an-array'], ['ui_schema']],
]);

test('form template name must be unique on creation', function () {
    FormTemplate::factory()->create(['name' => 'Existing Form']);

    $response = $this->postJson('/api/v1/form-templates', [
        'name' => 'Existing Form',
        'json_schema' => ['type' => 'object'],
        'ui_schema' => [],
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('form template name must be unique on update', function () {
    FormTemplate::factory()->create(['name' => 'Existing Form']);
    $template = FormTemplate::factory()->create(['name' => 'Another Form']);

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'Existing Form',
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('form template name can be updated to same name', function () {
    $template = FormTemplate::factory()->create(['name' => 'My Form']);

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'name' => 'My Form',
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'My Form')
        ->assertJsonPath('data.is_active', false);
});

test('unauthenticated user cannot access form template routes', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/form-templates'],
    'store' => ['POST', '/api/v1/form-templates'],
    'show' => ['GET', '/api/v1/form-templates/1'],
    'update' => ['PUT', '/api/v1/form-templates/1'],
]);

test('show non-existent form template returns 404', function () {
    $response = $this->getJson('/api/v1/form-templates/999', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

test('update non-existent form template returns 404', function () {
    $response = $this->putJson('/api/v1/form-templates/999', [
        'json_schema' => ['type' => 'object'],
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

test('form templates list is paginated correctly', function () {
    FormTemplate::factory(20)->create();

    $response = $this->getJson('/api/v1/form-templates?per_page=5', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 20)
        ->assertJsonPath('meta.per_page', 5);
});

test('non-admin cannot show inactive form template', function () {
    $template = FormTemplate::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertForbidden();
});

test('admin can show inactive form template', function () {
    $admin = User::factory()->create();
    $role = Role::factory()->create(['name' => 'admin', 'is_active' => true]);
    $admin->update(['role_id' => $role->id]);
    $adminToken = Auth::guard('api')->tokenById($admin->id);

    $template = FormTemplate::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/form-templates/{$template->id}", [
        'Authorization' => "Bearer $adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $template->id)
        ->assertJsonPath('data.is_active', false);
});

test('user can update form template with partial data', function () {
    $originalSchema = [
        'type' => 'object',
        'properties' => ['a' => ['type' => 'string']],
    ];
    $template = FormTemplate::factory()->create([
        'name' => 'Original',
        'json_schema' => $originalSchema,
        'ui_schema' => ['ui:order' => ['a']],
        'is_active' => true,
    ]);

    $response = $this->putJson("/api/v1/form-templates/{$template->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Original')
        ->assertJsonPath('data.json_schema', $originalSchema)
        ->assertJsonPath('data.ui_schema', ['ui:order' => ['a']])
        ->assertJsonPath('data.is_active', false);
});
