<?php

use App\Enums\SubmissionStatus;
use App\Models\FormSubmission;
use App\Models\FormTemplate;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::factory()->create(['name' => 'admin', 'is_active' => true]);
    $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
    $this->adminToken = Auth::guard('api')->tokenById($this->admin->id);

    $this->user = User::factory()->create();
    $this->token = Auth::guard('api')->tokenById($this->user->id);

    $this->template = FormTemplate::factory()->create();
    $this->submission = FormSubmission::factory()->create([
        'form_template_id' => $this->template->id,
        'status' => SubmissionStatus::Draft,
    ]);
});

// ─── Submit ────────────────────────────────────────────────────────────────

test('user can submit a draft submission for approval', function () {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/submit", [], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'pending_approval');

    $this->assertDatabaseHas('form_submissions', [
        'id' => $this->submission->id,
        'status' => 'pending_approval',
    ]);
});

test('submit returns 422 when submission is not in draft state', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/submit", [], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertUnprocessable();
});

test('unauthenticated user cannot submit a submission', function () {
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/submit");

    $response->assertUnauthorized();
});

// ─── Approve ───────────────────────────────────────────────────────────────

test('admin can approve a pending submission', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/approve", [], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->assertDatabaseHas('form_submissions', [
        'id' => $this->submission->id,
        'status' => 'approved',
    ]);
});

test('non-admin cannot approve a submission', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/approve", [], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertForbidden();
});

test('approve returns 422 when submission is not pending approval', function () {
    // Submission is in draft state by default
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/approve", [], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertUnprocessable();
});

// ─── Reject ────────────────────────────────────────────────────────────────

test('admin can reject a pending submission', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/reject", [
        'reason' => 'Missing required fields.',
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseHas('form_submissions', [
        'id' => $this->submission->id,
        'status' => 'rejected',
    ]);
});

test('admin can reject a pending submission without a reason', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/reject", [], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('non-admin cannot reject a submission', function () {
    $this->submission->update(['status' => SubmissionStatus::PendingApproval]);

    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/reject", [], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertForbidden();
});

test('reject returns 422 when submission is not pending approval', function () {
    // Submission is in draft state by default
    $response = $this->postJson("/api/v1/form-submissions/{$this->submission->id}/reject", [], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertUnprocessable();
});

// ─── Edit after approval ────────────────────────────────────────────────────

test('edit is blocked on an approved submission', function () {
    $this->submission->update(['status' => SubmissionStatus::Approved]);
    $v1 = $this->submission->versions()->create([
        'user_id' => $this->user->id,
        'form_name' => 'Test',
        'content' => ['v' => 1],
        'version_number' => 1,
    ]);
    $this->submission->update(['current_version_id' => $v1->id]);

    $response = $this->putJson("/api/v1/form-submissions/{$this->submission->id}", [
        'form_name' => 'Test',
        'content' => ['v' => 2],
        'version_number' => 1,
    ], [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertForbidden();
});

// ─── Status filter ──────────────────────────────────────────────────────────

test('index can be filtered by status', function () {
    FormSubmission::factory(2)->create(['status' => SubmissionStatus::Draft]);
    FormSubmission::factory(3)->create(['status' => SubmissionStatus::PendingApproval]);

    // The $this->submission from beforeEach is Draft (total draft = 3)
    $response = $this->getJson('/api/v1/form-submissions?status=draft', [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertOk()
        ->assertJsonCount(3, 'data');
});

// ─── Status in response ─────────────────────────────────────────────────────

test('form submission response includes status field', function () {
    $response = $this->getJson("/api/v1/form-submissions/{$this->submission->id}", [
        'Authorization' => "Bearer $this->token",
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'draft');
});
