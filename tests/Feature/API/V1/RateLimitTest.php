<?php

declare(strict_types=1);

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('login attempts are rate limited', function () {
    User::factory()->create([
        'email' => 'test@example.com',
    ]);

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();
    }

    $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ])->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});

test('message sending is rate limited per user', function () {
    Event::fake([MessageSent::class]);

    $user = User::factory()->create();
    $other = User::factory()->create();
    $conversation = Conversation::factory()->between($user, $other)->create();

    Sanctum::actingAs($user);

    RateLimiter::for('messages', fn (Request $request) => Limit::perMinute(1)
        ->by($request->user()?->id ?: $request->ip()));

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'First',
    ])->assertCreated();

    $this->postJson("/api/v1/conversations/{$conversation->id}/messages", [
        'body' => 'Second',
    ])->assertTooManyRequests()
        ->assertJsonPath('code', 'too_many_requests');
});
