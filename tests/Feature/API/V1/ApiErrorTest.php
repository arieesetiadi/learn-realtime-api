<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('unauthenticated errors use the API error contract', function () {
    $response = $this->getJson('/api/v1/user');

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonPath('message', 'Unauthenticated.')
        ->assertJsonStructure(['message', 'code', 'request_id'])
        ->assertHeader('X-Request-Id');

    expect($response->json('request_id'))->toBe($response->headers->get('X-Request-Id'));
});

test('forbidden errors use the API error contract', function () {
    $user = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $conversation = Conversation::factory()->between($first, $second)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden')
        ->assertJsonPath('message', 'Forbidden.');
});

test('missing resources use the API error contract', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/conversations/999999/messages')
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found')
        ->assertJsonPath('message', 'Resource not found.');
});

test('validation errors keep Laravel errors and add the API code', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => '',
    ])->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('body')
        ->assertJsonStructure(['message', 'errors', 'code', 'request_id']);
});

test('unexpected errors do not leak exception messages', function () {
    Route::middleware('api')->get('/_test/boom', fn () => throw new RuntimeException('secret internals'));

    $this->getJson('/_test/boom')
        ->assertStatus(500)
        ->assertJsonPath('code', 'server_error')
        ->assertJsonPath('message', 'Server Error.');
});
