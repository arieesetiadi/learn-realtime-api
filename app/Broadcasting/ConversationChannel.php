<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\Conversation;
use App\Models\User;

/**
 * Authorizes access to a private conversation channel.
 */
final class ConversationChannel
{
    /**
     * Determine whether the user participates in the conversation.
     */
    public function join(User $user, string $conversationId): bool
    {
        return Conversation::query()
            ->forUser($user->id)
            ->whereKey($conversationId)
            ->exists();
    }
}
