<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('user can start a conversation', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/conversations', [
        'recipient_id' => $recipient->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.other_user.id', $recipient->id)
        ->assertJsonPath('data.last_message', null);

    $this->assertDatabaseHas('conversations', [
        'user_one_id' => min($user->id, $recipient->id),
        'user_two_id' => max($user->id, $recipient->id),
    ]);
});

test('starting an existing conversation returns the same conversation', function () {
    $user = User::factory()->create();
    $recipient = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $recipient)->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/conversations', [
        'recipient_id' => $recipient->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $conversation->id);

    expect(Conversation::count())->toBe(1);
});

test('user cannot start a conversation with themselves', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/conversations', [
        'recipient_id' => $user->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('recipient_id');
});

test('conversation list only includes conversations the user participates in', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $stranger = User::factory()->create();
    $anotherStranger = User::factory()->create();

    $mine = Conversation::factory()->between($user, $other)->create();
    Conversation::factory()->between($stranger, $anotherStranger)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.other_user.id', $other->id);
});

test('conversation list is ordered by latest activity', function () {
    $user = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $older = Conversation::factory()->between($user, $first)->create();
    $newer = Conversation::factory()->between($user, $second)->create();

    $older->timestamps = false;
    $older->forceFill(['updated_at' => now()->subDay()])->save();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/conversations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

test('unauthenticated users cannot access conversations', function () {
    $this->getJson('/api/v1/conversations')->assertUnauthorized();
});
