<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::factory()->create(['name' => 'admin']);
    $this->managerRole = Role::factory()->create(['name' => 'manager']);
    $this->userRole = Role::factory()->create(['name' => 'user']);

    $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
    $this->adminToken = $this->admin->createToken('test')->plainTextToken;

    $this->manager = User::factory()->create(['role_id' => $this->managerRole->id]);
    $this->managerToken = $this->manager->createToken('test')->plainTextToken;

    $this->user = User::factory()->create(['role_id' => $this->userRole->id]);
    $this->userToken = $this->user->createToken('test')->plainTextToken;

    $this->department = Department::factory()->create();
});

test('admin can list teams', function () {
    Team::factory(2)->create(['department_id' => $this->department->id, 'is_active' => true]);
    Team::factory()->create(['department_id' => $this->department->id, 'is_active' => false]);

    $response = $this->getJson('/api/v1/teams', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('manager can list teams', function () {
    Team::factory(2)->create(['department_id' => $this->department->id, 'is_active' => true]);
    Team::factory()->create(['department_id' => $this->department->id, 'is_active' => false]);

    $response = $this->getJson('/api/v1/teams', [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('user can list teams', function () {
    Team::factory(2)->create(['department_id' => $this->department->id, 'is_active' => true]);
    Team::factory()->create(['department_id' => $this->department->id, 'is_active' => false]);

    $response = $this->getJson('/api/v1/teams', [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('team search filter returns matching teams only', function () {
    $matchedTeam = Team::factory()->create([
        'name' => 'Backend Alpha',
        'department_id' => $this->department->id,
    ]);
    Team::factory()->create([
        'name' => 'Frontend Beta',
        'department_id' => $this->department->id,
    ]);

    $response = $this->getJson('/api/v1/teams?search=Backend', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchedTeam->id);
});

test('admin can show team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->getJson("/api/v1/teams/{$team->id}", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $team->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'department']]);
});

test('manager can show team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->getJson("/api/v1/teams/{$team->id}", [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $team->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'department']]);
});

test('user can show team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id, 'is_active' => true]);

    $response = $this->getJson("/api/v1/teams/{$team->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $team->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'department']]);
});

test('non-admin cannot show inactive team', function () {
    $team = Team::factory()->create([
        'department_id' => $this->department->id,
        'is_active' => false,
    ]);

    $response = $this->getJson("/api/v1/teams/{$team->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can create team', function () {
    $response = $this->postJson('/api/v1/teams', [
        'name' => 'Backend Team',
        'department_id' => $this->department->id,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Backend Team');
});

test('non-admin cannot create team', function () {
    $response = $this->postJson('/api/v1/teams', [
        'name' => 'Backend Team',
        'department_id' => $this->department->id,
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can update team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'name' => 'Frontend Team',
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Frontend Team');
});

test('non-admin cannot update team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id]);

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'name' => 'Frontend Team',
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can deactivate team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id, 'is_active' => true]);

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('teams', ['id' => $team->id, 'is_active' => false]);
});

test('non-admin cannot deactivate team', function () {
    $team = Team::factory()->create(['department_id' => $this->department->id, 'is_active' => true]);

    $response = $this->putJson("/api/v1/teams/{$team->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('team validation', function (array $data, array $errors) {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/teams', $data, [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [
        fn () => ['department_id' => Department::factory()->create()->id],
        ['name'],
    ],
    'missing department_id' => [
        ['name' => 'Test Team'],
        ['department_id'],
    ],
    'invalid department_id' => [
        ['name' => 'Test Team', 'department_id' => 999],
        ['department_id'],
    ],
]);

test('unauthenticated user cannot access team routes', function (string $method, string $uri) {
    /** @var TestCase $this */
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/teams'],
    'store' => ['POST', '/api/v1/teams'],
    'show' => ['GET', '/api/v1/teams/1'],
    'update' => ['PUT', '/api/v1/teams/1'],
]);

test('show non-existent team returns 404', function () {
    $response = $this->getJson('/api/v1/teams/999', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertNotFound();
});
