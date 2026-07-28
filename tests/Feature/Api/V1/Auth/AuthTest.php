<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

uses(RefreshDatabase::class);

test('user can login with correct credentials', function () {
    /** @var TestCase $this */
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});

test('user cannot login with incorrect credentials', function () {
    /** @var TestCase $this */
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Wrong email or password, please try again.']);
});

test('authenticated user can get their profile', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->getJson('/api/v1/auth/me', [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('data.email', $user->email);
});

test('authenticated user can logout', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/auth/logout', [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertStatus(200)
        ->assertJson(['message' => 'Successfully logged out']);
});

test('authenticated user can refresh token', function () {
    /** @var TestCase $this */
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/auth/refresh', [], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});

test('authenticated user can change password', function () {
    /** @var TestCase $this */
    $user = User::factory()->create([
        'password' => Hash::make('oldpassword'),
    ]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/auth/change-password', [
        'current_password' => 'oldpassword',
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ], [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertSuccessful()
        ->assertJson(['message' => 'Password changed successfully']);

    $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
});

test('login validation', function (array $data, array $errors) {
    /** @var TestCase $this */
    $response = $this->postJson('/api/v1/auth/login', $data);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'missing email' => [['password' => 'password123'], ['email']],
    'missing password' => [['email' => 'test@example.com'], ['password']],
    'invalid email' => [['email' => 'not-an-email', 'password' => 'password123'], ['email']],
]);

test('unauthenticated user cannot access protected auth routes', function (string $method, string $uri) {
    /** @var TestCase $this */
    $response = $this->json($method, $uri);

    $response->assertUnauthorized();
})->with([
    'me' => ['GET', '/api/v1/auth/me'],
    'logout' => ['POST', '/api/v1/auth/logout'],
    'refresh' => ['POST', '/api/v1/auth/refresh'],
    'change-password' => ['POST', '/api/v1/auth/change-password'],
]);

test('change password validation', function (array $data, array $errors) {
    /** @var TestCase $this */
    $user = User::factory()->create(['password' => Hash::make('oldpassword')]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->postJson('/api/v1/auth/change-password', $data, [
        'Authorization' => "Bearer $token",
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors($errors);
})->with([
    'wrong current password' => [
        [
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ],
        ['current_password'],
    ],
    'mismatched confirmation' => [
        [
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'different',
        ],
        ['password'],
    ],
    'too short password' => [
        [
            'current_password' => 'oldpassword',
            'password' => 'short',
            'password_confirmation' => 'short',
        ],
        ['password'],
    ],
]);

test('inactive user cannot login', function () {
    /** @var TestCase $this */
    User::factory()->create([
        'email' => 'inactive@example.com',
        'password' => Hash::make('password123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);

    $response->assertUnauthorized()
        ->assertJson(['error' => 'Your account is inactive. Please contact your administrator.']);
});
