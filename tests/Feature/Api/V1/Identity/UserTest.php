<?php

use App\Models\Department;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var TestCase $this */
    $this->adminRole = Role::factory()->create(['name' => 'admin']);
    $this->managerRole = Role::factory()->create(['name' => 'manager']);
    $this->userRole = Role::factory()->create(['name' => 'user']);

    $this->admin = User::factory()->create(['role_id' => $this->adminRole->id]);
    $this->adminToken = $this->admin->createToken('test')->plainTextToken;

    $this->manager = User::factory()->create(['role_id' => $this->managerRole->id]);
    $this->managerToken = $this->manager->createToken('test')->plainTextToken;

    $this->user = User::factory()->create(['role_id' => $this->userRole->id]);
    $this->userToken = $this->user->createToken('test')->plainTextToken;
});

test('admin can list users', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/api/v1/users', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('manager can list users', function () {
    /** @var TestCase $this */
    $activeUser = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/users', [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('data.0.is_active', true);
});

test('user can list users', function () {
    /** @var TestCase $this */
    $activeUser = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/users', [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'links', 'meta'])
        ->assertJsonPath('data.0.is_active', true);
});

test('admin can show user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();

    $response = $this->getJson("/api/v1/users/{$targetUser->id}", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $targetUser->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'departments', 'teams', 'role']]);
});

test('manager can show user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();

    $response = $this->getJson("/api/v1/users/{$targetUser->id}", [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $targetUser->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'departments', 'teams', 'role']]);
});

test('user can show user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/users/{$targetUser->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $targetUser->id)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'departments', 'teams', 'role']]);
});

test('non-admin cannot show inactive user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/users/{$targetUser->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can create user', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
        'role_id' => $this->userRole->id,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'newuser@example.com');

    $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
});

test('admin can create user with departments and teams', function () {
    /** @var TestCase $this */
    $dept = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $dept->id]);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Identity User',
        'email' => 'identity@example.com',
        'password' => 'password123',
        'department_ids' => [$dept->id],
        'team_ids' => [$team->id],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.departments.0.id', $dept->id)
        ->assertJsonPath('data.teams.0.id', $team->id);
});

test('non-admin cannot create user', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'newuser@example.com',
        'password' => 'password123',
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('manager cannot create user', function () {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users', [
        'name' => 'New User',
        'email' => 'newmanageruser@example.com',
        'password' => 'password123',
    ], [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertForbidden();
});

test('admin can update user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();

    $response = $this->putJson("/api/v1/users/{$targetUser->id}", [
        'name' => 'Updated Name',
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'Updated Name');
});

test('non-admin cannot update user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();

    $response = $this->putJson("/api/v1/users/{$targetUser->id}", [
        'name' => 'Updated Name',
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can deactivate user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/v1/users/{$targetUser->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('users', ['id' => $targetUser->id, 'is_active' => false]);
});

test('non-admin cannot deactivate user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/v1/users/{$targetUser->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can sync user departments', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $dept = Department::factory()->create();

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/departments", [
        'department_ids' => [$dept->id],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJson(['message' => 'Departments synced successfully']);

    $this->assertTrue($targetUser->fresh()->departments->contains($dept->id));
});

test('admin can sync user teams', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/teams", [
        'team_ids' => [$team->id],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJson(['message' => 'Teams synced successfully']);

    $this->assertTrue($targetUser->fresh()->teams->contains($team->id));
});

test('user filtering', function (string $filter, $value, int $expectedCount) {
    /** @var TestCase $this */
    $dept = Department::factory()->create();
    $team = Team::factory()->create(['department_id' => $dept->id]);
    $role = Role::factory()->create();

    $u = User::factory()->create([
        'is_active' => true,
        'role_id' => $role->id,
    ]);
    $u->departments()->attach($dept);
    $u->teams()->attach($team);

    // Filter values might need to be resolved if they are closures
    $val = is_callable($value) ? $value($dept, $team, $role) : $value;

    $response = $this->getJson("/api/v1/users?{$filter}={$val}", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount($expectedCount, 'data');
})->with([
    'by department_id' => ['department_id', fn ($d) => $d->id, 1],
    'by team_id' => ['team_id', fn ($d, $t) => $t->id, 1],
    'by is_active' => ['is_active', 1, 4], // admin, manager, user, and the new one
    'by role_id' => ['role_id', fn ($d, $t, $r) => $r->id, 1],
]);

test('user search filter works for name and email', function () {
    /** @var TestCase $this */
    $searchedUser = User::factory()->create([
        'name' => 'Alice Example',
        'email' => 'alice@example.test',
    ]);
    $otherUser = User::factory()->create([
        'name' => 'Bob Other',
        'email' => 'bob@other.test',
    ]);

    $response = $this->getJson('/api/v1/users?search=Alice', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $searchedUser->id);

    $response = $this->getJson('/api/v1/users?search=other.test', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $otherUser->id);
});

test('password is hashed when creating user', function () {
    /** @var TestCase $this */
    $plainPassword = 'plain-password-123';

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Hashed User',
        'email' => 'hash-create@example.com',
        'password' => $plainPassword,
        'role_id' => $this->userRole->id,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated();

    $user = User::where('email', 'hash-create@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->password)->not->toBe($plainPassword);
    expect(Hash::check($plainPassword, $user->password))->toBeTrue();
});

test('password is hashed when updating user', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create([
        'password' => bcrypt('old-password'),
    ]);
    $newPassword = 'new-password-456';

    $response = $this->putJson("/api/v1/users/{$targetUser->id}", [
        'password' => $newPassword,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();

    $targetUser->refresh();

    expect(Hash::check($newPassword, $targetUser->password))->toBeTrue();
});

test('user validation', function (array $data, array $errors) {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/users', $data, [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [['email' => 'test@example.com', 'password' => 'password123'], ['name']],
    'missing email' => [['name' => 'Test', 'password' => 'password123'], ['email']],
    'invalid email' => [['name' => 'Test', 'email' => 'not-an-email', 'password' => 'password123'], ['email']],
    'duplicate email' => [
        fn () => ['name' => 'Test', 'email' => User::factory()->create()->email, 'password' => 'password123'],
        ['email'],
    ],
    'invalid role_id' => [['name' => 'Test', 'email' => 't@e.com', 'password' => 'p123', 'role_id' => 999], ['role_id']],
]);

test('admin can create user with multiple departments and teams', function () {
    /** @var TestCase $this */
    $dept1 = Department::factory()->create();
    $dept2 = Department::factory()->create();
    $team1 = Team::factory()->create(['department_id' => $dept1->id]);
    $team2 = Team::factory()->create(['department_id' => $dept2->id]);

    $response = $this->postJson('/api/v1/users', [
        'name' => 'Multi Identity User',
        'email' => 'multi@example.com',
        'password' => 'password123',
        'department_ids' => [$dept1->id, $dept2->id],
        'team_ids' => [$team1->id, $team2->id],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated()
        ->assertJsonCount(2, 'data.departments')
        ->assertJsonCount(2, 'data.teams');
});

test('admin can sync user departments with empty array', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $dept = Department::factory()->create();
    $targetUser->departments()->attach($dept);

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/departments", [
        'department_ids' => [],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();
    $this->assertCount(0, $targetUser->fresh()->departments);
});

test('admin can sync user teams with empty array', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $team = Team::factory()->create();
    $targetUser->teams()->attach($team);

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/teams", [
        'team_ids' => [],
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();
    $this->assertCount(0, $targetUser->fresh()->teams);
});

test('admin can get user departments', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $dept = Department::factory()->create();
    $targetUser->departments()->attach($dept);

    $response = $this->getJson("/api/v1/users/{$targetUser->id}/departments", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $dept->id);
});

test('admin can get user teams', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $team = Team::factory()->create();
    $targetUser->teams()->attach($team);

    $response = $this->getJson("/api/v1/users/{$targetUser->id}/teams", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $team->id);
});

test('non-admin cannot sync user departments', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $dept = Department::factory()->create();

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/departments", [
        'department_ids' => [$dept->id],
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('non-admin cannot sync user teams', function () {
    /** @var TestCase $this */
    $targetUser = User::factory()->create();
    $team = Team::factory()->create();

    $response = $this->postJson("/api/v1/users/{$targetUser->id}/teams", [
        'team_ids' => [$team->id],
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('unauthenticated user cannot access user routes', function (string $method, string $uri) {
    /** @var TestCase $this */
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/users'],
    'store' => ['POST', '/api/v1/users'],
    'show' => ['GET', '/api/v1/users/1'],
    'update' => ['PUT', '/api/v1/users/1'],
    'sync-departments' => ['POST', '/api/v1/users/1/departments'],
    'get-departments' => ['GET', '/api/v1/users/1/departments'],
    'sync-teams' => ['POST', '/api/v1/users/1/teams'],
    'get-teams' => ['GET', '/api/v1/users/1/teams'],
]);
