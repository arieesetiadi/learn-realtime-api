<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
     * Find or create the direct conversation with the given recipient.
     *
     * The recipient is resolved by ID or email. An unknown recipient and a
     * recipient equal to the actor both raise validation errors (422) rather
     * than 404, so the endpoint does not reveal which accounts exist.
     */
    public function findOrCreateWithRecipient(
        int $userId,
        ?int $recipientId,
        ?string $recipientEmail,
    ): Conversation {
        $field = $recipientId !== null ? 'recipient_id' : 'recipient_email';

        $recipient = $recipientId !== null
            ? User::query()->find($recipientId)
            : User::query()->where('email', (string) $recipientEmail)->first();

        if ($recipient === null) {
            throw ValidationException::withMessages([
                $field => [
                    $recipientId !== null
                        ? 'No user found with that ID.'
                        : 'No user found with that email.',
                ],
            ]);
        }

        if ($recipient->id === $userId) {
            throw ValidationException::withMessages([
                $field => ['You cannot start a conversation with yourself.'],
            ]);
        }

        return $this->findOrCreateBetween($userId, $recipient->id);
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
