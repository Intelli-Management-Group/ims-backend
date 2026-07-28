<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    /** @var Tests\TestCase $this */
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

test('admin can list roles', function () {
    /** @var Tests\TestCase $this */
    Role::factory(2)->create(['is_active' => true]);
    Role::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/roles', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(6, 'data'); // 4 active + 2 default (admin, manager, user are 3 total; one already created)
});

test('manager can list roles', function () {
    /** @var Tests\TestCase $this */
    Role::factory(2)->create(['is_active' => true]);
    Role::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/roles', [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data'); // 4 active + 1 default (manager)
});

test('user can list roles', function () {
    /** @var Tests\TestCase $this */
    Role::factory(2)->create(['is_active' => true]);
    Role::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/roles', [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(5, 'data'); // 4 active + 1 default (user)
});

test('role search filter returns matching roles only', function () {
    /** @var Tests\TestCase $this */
    $matchedRole = Role::factory()->create(['name' => 'search-role-matched']);
    Role::factory()->create(['name' => 'other-role']);

    $response = $this->getJson('/api/v1/roles?search=search-role-matched', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $matchedRole->id);
});

test('admin can show role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create();

    $response = $this->getJson("/api/v1/roles/{$role->id}", [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $role->id);
});

test('manager can show role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create();

    $response = $this->getJson("/api/v1/roles/{$role->id}", [
        'Authorization' => "Bearer $this->managerToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $role->id);
});

test('user can show role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/roles/{$role->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $role->id);
});

test('non-admin cannot show inactive role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/roles/{$role->id}", [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can create role', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/v1/roles', [
        'name' => 'custom-manager',
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'custom-manager');
});

test('non-admin cannot create role', function () {
    /** @var Tests\TestCase $this */
    $response = $this->postJson('/api/v1/roles', [
        'name' => 'manager',
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can update role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create();

    $response = $this->putJson("/api/v1/roles/{$role->id}", [
        'name' => 'New Role Name',
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('data.name', 'New Role Name');
});

test('non-admin cannot update role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create();

    $response = $this->putJson("/api/v1/roles/{$role->id}", [
        'name' => 'New Role Name',
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('admin can deactivate role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/v1/roles/{$role->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('roles', ['id' => $role->id, 'is_active' => false]);
});

test('non-admin cannot deactivate role', function () {
    /** @var Tests\TestCase $this */
    $role = Role::factory()->create(['is_active' => true]);

    $response = $this->putJson("/api/v1/roles/{$role->id}", [
        'is_active' => false,
    ], [
        'Authorization' => "Bearer $this->userToken",
    ]);

    $response->assertForbidden();
});

test('role validation', function (array $data, array $errors) {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/roles', $data, [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing name' => [[], ['name']],
    'name too long' => [['name' => str_repeat('a', 256)], ['name']],
    'duplicate name' => [
        fn () => ['name' => Role::factory()->create(['name' => 'custom-role'])->name],
        ['name'],
    ],
]);

test('unauthenticated user cannot access role routes', function (string $method, string $uri) {
    /** @var TestCase $this */
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'index' => ['GET', '/api/v1/roles'],
    'store' => ['POST', '/api/v1/roles'],
    'show' => ['GET', '/api/v1/roles/1'],
    'update' => ['PUT', '/api/v1/roles/1'],
]);

test('show non-existent role returns 404', function () {
    /** @var Tests\TestCase $this */
    $response = $this->getJson('/api/v1/roles/999', [
        'Authorization' => "Bearer $this->adminToken",
    ]);

    $response->assertNotFound();
});
