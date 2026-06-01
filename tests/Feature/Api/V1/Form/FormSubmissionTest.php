<?php

use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = Auth::guard('api')->tokenById($this->user->id);
});

test('user can create form submission', function () {
    $template = FormTemplate::factory()->create();
    $data = [
        'form_template_id' => $template->id,
        'form_name' => $template->name,
        'content' => ['field' => 'value'],
    ];

    $response = $this->postJson('/api/v1/form-submissions', $data, [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.template.id', $template->id)
        ->assertJsonPath('data.current_version.version_number', 1)
        ->assertJsonPath('data.current_version.form_name', $template->name)
        ->assertJsonPath('data.current_version.content.field', 'value');
});

test('user can list form submissions', function () {
    FormSubmission::factory(3)->create();

    $response = $this->getJson('/api/v1/form-submissions', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('user can update form submission and create new version', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v1 = $submission->versions()->create([
        'user_id' => $this->user->id,
        'form_name' => $template->name,
        'content' => ['v' => 1],
        'version_number' => 1,
    ]);
    $submission->update(['current_version_id' => $v1->id]);

    $response = $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => $template->name,
        'content' => ['v' => 2],
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.current_version.version_number', 2)
        ->assertJsonPath('data.current_version.form_name', $template->name)
        ->assertJsonPath('data.current_version.content.v', 2);

    $this->assertDatabaseCount('form_submission_versions', 2);
});

test('update form submission returns 409 on version conflict', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v1 = $submission->versions()->create([
        'user_id' => $this->user->id,
        'form_name' => $template->name,
        'content' => ['v' => 1],
        'version_number' => 1,
    ]);
    $submission->update(['current_version_id' => $v1->id]);

    // Try to update with wrong version number
    $response = $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'form_name' => $template->name,
        'content' => ['v' => 2],
        'version_number' => 0, // Wrong version
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertStatus(409);
});

test('user can list versions for a submission', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 1], 'version_number' => 1]);
    $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 2], 'version_number' => 2]);

    $response = $this->getJson("/api/v1/form-submissions/{$submission->id}/versions", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('unauthenticated user cannot access form submission routes', function (string $method, string $uri) {
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/form-submissions'],
    'store' => ['POST', '/api/v1/form-submissions'],
    'show' => ['GET', '/api/v1/form-submissions/1'],
    'update' => ['PUT', '/api/v1/form-submissions/1'],
    'versions_index' => ['GET', '/api/v1/form-submissions/1/versions'],
    'versions_show' => ['GET', '/api/v1/form-submissions/1/versions/1'],
]);

test('show non-existent form submission returns 404', function () {
    $response = $this->getJson('/api/v1/form-submissions/999', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

test('form submission validation', function (array $data, array $errors) {
    $response = $this->postJson('/api/v1/form-submissions', $data, [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing template id' => [['form_name' => 'test', 'content' => []], ['form_template_id']],
    'invalid template id' => [['form_template_id' => 999, 'form_name' => 'test', 'content' => []], ['form_template_id']],
    'missing form name' => [['form_template_id' => 1, 'content' => []], ['form_name']],
    'missing content' => [['form_template_id' => 1, 'form_name' => 'test'], ['content']],
    'invalid content type' => [['form_template_id' => 1, 'form_name' => 'test', 'content' => 'not-an-array'], ['content']],
]);

test('update form submission validation', function (array $data, array $errors) {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);

    $response = $this->putJson("/api/v1/form-submissions/{$submission->id}", $data, [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing form name' => [['content' => [], 'version_number' => 1], ['form_name']],
    'missing content' => [['form_name' => 'test', 'version_number' => 1], ['content']],
    'missing version_number' => [['form_name' => 'test', 'content' => []], ['version_number']],
    'invalid version_number type' => [['form_name' => 'test', 'content' => [], 'version_number' => 'one'], ['version_number']],
]);

test('list form submissions can be filtered by form_template_id', function () {
    $template1 = FormTemplate::factory()->create();
    $template2 = FormTemplate::factory()->create();

    FormSubmission::factory(2)->create(['form_template_id' => $template1->id]);
    FormSubmission::factory(3)->create(['form_template_id' => $template2->id]);

    $response = $this->getJson("/api/v1/form-submissions?form_template_id={$template1->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('user can show specific version of a submission', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v1 = $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 1], 'version_number' => 1]);

    $response = $this->getJson("/api/v1/form-submissions/{$submission->id}/versions/{$v1->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $v1->id)
        ->assertJsonPath('data.version_number', 1);
});

test('show version returns 404 if version does not belong to submission', function () {
    $template = FormTemplate::factory()->create();
    $submission1 = FormSubmission::create(['form_template_id' => $template->id]);
    $submission2 = FormSubmission::create(['form_template_id' => $template->id]);

    $v1 = $submission1->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 1], 'version_number' => 1]);

    $response = $this->getJson("/api/v1/form-submissions/{$submission2->id}/versions/{$v1->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertNotFound();
});

test('form submissions list is paginated correctly', function () {
    $template = FormTemplate::factory()->create();
    foreach (range(1, 20) as $i) {
        FormSubmission::factory()->create(['form_template_id' => $template->id]);
    }

    $response = $this->getJson('/api/v1/form-submissions?per_page=5', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.per_page', 5);
});

test('form submissions list per_page is bounded between 1 and 100', function () {
    FormSubmission::factory()->create();

    $response = $this->getJson('/api/v1/form-submissions?per_page=0', [
        'Authorization' => "Bearer $this->token",
    ]);
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1);

    $response = $this->getJson('/api/v1/form-submissions?per_page=101', [
        'Authorization' => "Bearer $this->token",
    ]);
    $response->assertSuccessful();
    expect($response->json('meta.per_page'))->toBe(100);
});

test('versions list returns versions ordered by version_number descending', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 1], 'version_number' => 1]);
    $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 2], 'version_number' => 2]);
    $submission->versions()->create(['user_id' => $this->user->id, 'form_name' => $template->name, 'content' => ['v' => 3], 'version_number' => 3]);

    $response = $this->getJson("/api/v1/form-submissions/{$submission->id}/versions", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.version_number', 3)
        ->assertJsonPath('data.1.version_number', 2)
        ->assertJsonPath('data.2.version_number', 1);
});

test('update rejects conflict based on current db state not stale route model', function () {
    $template = FormTemplate::factory()->create();
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v1 = $submission->versions()->create([
        'user_id' => $this->user->id,
        'content' => ['v' => 1],
        'version_number' => 1,
    ]);
    $submission->update(['current_version_id' => $v1->id]);

    // Simulate a concurrent write advancing the version in the DB
    $v2 = $submission->versions()->create([
        'user_id' => $this->user->id,
        'content' => ['v' => 2],
        'version_number' => 2,
    ]);
    $submission->update(['current_version_id' => $v2->id]);

    // Our request still believes it is updating from version 1 — should 409
    $response = $this->putJson("/api/v1/form-submissions/{$submission->id}", [
        'content' => ['v' => 3],
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertStatus(409);
    $this->assertDatabaseCount('form_submission_versions', 2);
});

test('show form submission returns template and current version', function () {
    $template = FormTemplate::factory()->create(['name' => 'Test Template']);
    $submission = FormSubmission::create(['form_template_id' => $template->id]);
    $v1 = $submission->versions()->create([
        'user_id' => $this->user->id,
        'form_name' => 'Test Template',
        'content' => ['answer' => 'foo'],
        'version_number' => 1,
    ]);
    $submission->update(['current_version_id' => $v1->id]);

    $response = $this->getJson("/api/v1/form-submissions/{$submission->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.template.id', $template->id)
        ->assertJsonPath('data.template.name', 'Test Template')
        ->assertJsonPath('data.current_version.version_number', 1)
        ->assertJsonPath('data.current_version.form_name', 'Test Template')
        ->assertJsonPath('data.current_version.content.answer', 'foo')
        ->assertJsonPath('data.submitted_by', $this->user->name);
});
