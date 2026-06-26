<?php

use App\Enums\AssigneeScope;
use App\Models\Department;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->token = Auth::guard('api')->tokenById($this->user->id);

    $adminRole = Role::factory()->create(['name' => 'admin', 'is_active' => true]);
    $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
    $this->adminToken = Auth::guard('api')->tokenById($this->admin->id);

    $this->template = FormTemplate::factory()->create();
    $this->submission = FormSubmission::factory()->create(['form_template_id' => $this->template->id]);
});

test('admin can assign a submission to a user', function (): void {
    $assignee = User::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => $assignee->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful()
        ->assertJsonPath('data.assignee_type', 'user')
        ->assertJsonPath('data.assignee_id', $assignee->id)
        ->assertJsonPath('data.assignee.type', 'user')
        ->assertJsonPath('data.assignee.id', $assignee->id)
        ->assertJsonPath('data.assignee.name', $assignee->name);

    $this->assertDatabaseHas('form_submissions', [
        'id' => $this->submission->id,
        'assignee_type' => 'user',
        'assignee_id' => $assignee->id,
    ]);
});

test('admin can assign a submission to a team', function (): void {
    $team = Team::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'team',
        'assignee_id' => $team->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful()
        ->assertJsonPath('data.assignee_type', 'team')
        ->assertJsonPath('data.assignee_id', $team->id)
        ->assertJsonPath('data.assignee.name', $team->name);
});

test('admin can assign a submission to a department', function (): void {
    $department = Department::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'department',
        'assignee_id' => $department->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful()
        ->assertJsonPath('data.assignee_type', 'department')
        ->assertJsonPath('data.assignee_id', $department->id)
        ->assertJsonPath('data.assignee.name', $department->name);
});

test('admin can unassign a submission by sending null', function (): void {
    $assignee = User::factory()->create();
    $this->submission->update(['assignee_type' => 'user', 'assignee_id' => $assignee->id]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => null,
        'assignee_id' => null,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful()
        ->assertJsonPath('data.assignee_type', null)
        ->assertJsonPath('data.assignee_id', null);

    $this->assertDatabaseHas('form_submissions', [
        'id' => $this->submission->id,
        'assignee_type' => null,
        'assignee_id' => null,
    ]);
});

test('non-admin cannot assign a submission', function (): void {
    $assignee = User::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => $assignee->id,
    ], ['Authorization' => "Bearer $this->token"]);

    $response->assertForbidden();
});

test('unauthenticated user cannot assign a submission', function (): void {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => 1,
    ]);

    $response->assertUnauthorized();
});

test('assign fails with invalid assignee_type', function (): void {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'invalid',
        'assignee_id' => 1,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_type']);
});

test('assign fails when assignee does not exist', function (): void {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => 99999,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_id']);
});

test('assign fails when only assignee_type is provided without assignee_id', function (): void {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => null,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_type']);
});

test('scope validation rejects wrong assignee_type when template has assignee_scope', function (): void {
    $this->template->update(['assignee_scope' => AssigneeScope::Team->value]);

    $user = User::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['assignee_type']);
});

test('scope validation allows matching assignee_type when template has assignee_scope', function (): void {
    $this->template->update(['assignee_scope' => AssigneeScope::Team->value]);

    $team = Team::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'team',
        'assignee_id' => $team->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful();
});

test('scope global allows any assignee_type', function (): void {
    $this->template->update(['assignee_scope' => AssigneeScope::Global->value]);

    $user = User::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'user',
        'assignee_id' => $user->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful();
});

test('scope null allows any assignee_type', function (): void {
    $this->assertNull($this->template->assignee_scope);

    $department = Department::factory()->create();

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/assign", [
        'assignee_type' => 'department',
        'assignee_id' => $department->id,
    ], ['Authorization' => "Bearer $this->adminToken"]);

    $response->assertSuccessful();
});
