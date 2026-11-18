<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    return Conversation::query()
        ->forUser($user->id)
        ->whereKey($conversationId)
        ->exists();
});
