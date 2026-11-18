<?php

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('participant can send a message', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    $response = $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Hello there',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.body', 'Hello there')
        ->assertJsonPath('data.sender.id', $user->id);

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'sender_id' => $user->id,
        'body' => 'Hello there',
    ]);

    Event::assertDispatched(MessageSent::class, function (MessageSent $event) use ($conversation, $user) {
        $channel = $event->broadcastOn()[0];

        return $event->message->conversation_id === $conversation->id
            && $event->message->sender_id === $user->id
            && $channel->name === 'private-conversation.'.$conversation->id
            && $event->broadcastAs() === 'MessageSent'
            && $event->broadcastWith()['body'] === 'Hello there';
    });
});

test('sending a message updates the conversation activity timestamp', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    $conversation->timestamps = false;
    $conversation->forceFill(['updated_at' => now()->subDay()])->save();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Bump',
    ])->assertCreated();

    expect($conversation->fresh()->updated_at->isToday())->toBeTrue();
});

test('non participant cannot read or send messages', function () {
    $user = User::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();
    $conversation = Conversation::factory()->between($first, $second)->create();

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertForbidden();

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Not allowed',
    ])->assertForbidden();
});

test('message body is required and limited to 5000 characters', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors('body');

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => str_repeat('a', 5001),
    ])->assertUnprocessable()->assertJsonValidationErrors('body');
});

test('participant can list messages newest first', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    foreach (['First', 'Second', 'Third'] as $body) {
        Message::factory()->for($conversation)->create([
            'sender_id' => $user->id,
            'body' => $body,
        ]);
    }

    Sanctum::actingAs($user);

    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.body', 'Third')
        ->assertJsonPath('data.1.body', 'Second')
        ->assertJsonPath('data.2.body', 'First');
});

test('unauthenticated users cannot access messages', function () {
    $conversation = Conversation::factory()->create();

    $this->getJson("/api/v1/conversations/{$conversation->id}/messages")->assertUnauthorized();
});

test('broadcast channel authorization only allows participants', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $stranger = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    config(['broadcasting.default' => 'reverb']);

    Sanctum::actingAs($user);

    $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-conversation.'.$conversation->id,
        'socket_id' => '1234.5678',
    ])->assertOk()->assertJsonStructure(['auth']);

    Sanctum::actingAs($stranger);

    $this->postJson('/api/broadcasting/auth', [
        'channel_name' => 'private-conversation.'.$conversation->id,
        'socket_id' => '1234.5678',
    ])->assertForbidden();
});
