<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Log;

/**
 * Handles conversation use cases.
 *
 * Authorization is intentionally left to the controller/policy layer. This
 * service only reads and writes conversation data.
 */
final class ConversationService
{
    /**
     * List conversations for the given user, most recently active first.
     *
     * @return CursorPaginator<int, Conversation>
     */
    public function listForUser(int $userId): CursorPaginator
    {
        return Conversation::query()
            ->forUser($userId)
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->latest('updated_at')
            ->cursorPaginate(20);
    }

    /**
     * Find or create the direct conversation between two users.
     *
     * Participants are stored in ascending ID order so the unique index on
     * (user_one_id, user_two_id) makes the pair canonical. If two requests
     * race to create the same pair, the loser recovers by reading the winner's
     * row instead of failing the request.
     */
    public function findOrCreateBetween(int $userId, int $recipientId): Conversation
    {
        $userOneId = min($userId, $recipientId);
        $userTwoId = max($userId, $recipientId);

        try {
            $conversation = Conversation::firstOrCreate([
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);
        } catch (UniqueConstraintViolationException) {
            $conversation = Conversation::query()
                ->where('user_one_id', $userOneId)
                ->where('user_two_id', $userTwoId)
                ->firstOrFail();

            Log::debug('conversation.race_recovered', [
                'conversation_id' => $conversation->id,
                'user_one_id' => $userOneId,
                'user_two_id' => $userTwoId,
            ]);
        }

        $conversation->load(['userOne', 'userTwo', 'latestMessage.sender']);

        Log::info($conversation->wasRecentlyCreated ? 'conversation.created' : 'conversation.reused', [
            'conversation_id' => $conversation->id,
            'actor_id' => $userId,
            'recipient_id' => $recipientId,
        ]);

        return $conversation;
    }
}
