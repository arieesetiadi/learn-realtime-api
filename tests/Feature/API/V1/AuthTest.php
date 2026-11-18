<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can register', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.user.name', 'Test User')
        ->assertJsonPath('data.user.email', 'test@example.com')
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token',
            ],
        ]);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
    ]);

    $this->withToken($response->json('data.token'))
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('data.email', 'test@example.com');
});

test('registration rejects duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('user can login', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'name', 'email'],
                'token',
            ],
        ]);

    $this->withToken($response->json('data.token'))
        ->getJson('/api/v1/user')
        ->assertOk();
});

test('login rejects invalid credentials', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/login', [
        'email' => 'missing@example.com',
        'password' => 'password',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('user can logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth-token')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/logout')
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0);

    // Simulate a fresh request now that the token is revoked.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertUnauthorized();
});

test('logout requires authentication', function () {
    $this->postJson('/api/v1/logout')->assertUnauthorized();
});
