<?php

declare(strict_types=1);

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastingFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

test('request id is accepted from a valid header', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->withHeader('X-Request-Id', 'test-request-id-123')
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertHeader('X-Request-Id', 'test-request-id-123');
});

test('request id is generated when the header is missing or invalid', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->withHeader('X-Request-Id', 'short')
        ->getJson('/api/v1/user')
        ->assertOk();

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)
        ->not->toBe('short')
        ->not->toBeEmpty();
});

test('message logging includes identifiers but never the body', function () {
    Event::fake([MessageSent::class]);
    Log::spy();

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Hello there',
    ])->assertCreated();

    Log::shouldHaveReceived('info')
        ->with('message.sent', Mockery::on(function (array $context) use ($conversation, $user): bool {
            return $context['conversation_id'] === $conversation->id
                && $context['sender_id'] === $user->id
                && $context['body_length'] === 11
                && ! array_key_exists('body', $context);
        }))
        ->once();
});

test('broadcast failures do not fail a saved message', function () {
    Log::spy();

    $this->mock(BroadcastingFactory::class, function (MockInterface $mock): void {
        $mock->shouldReceive('queue')->andThrow(new RuntimeException('reverb is down'));
    });

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Still saved',
    ])->assertCreated();

    $this->assertDatabaseHas('messages', [
        'conversation_id' => $conversation->id,
        'body' => 'Still saved',
    ]);

    Log::shouldHaveReceived('warning')
        ->with('message.broadcast_failed', Mockery::on(function (array $context) use ($conversation): bool {
            return $context['conversation_id'] === $conversation->id
                && $context['exception'] instanceof RuntimeException;
        }))
        ->once();
});
