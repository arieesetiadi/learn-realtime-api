<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Handles message use cases.
 *
 * Message persistence is the source of truth. Realtime broadcasting is a
 * best-effort side effect: a broadcast outage must not fail a saved message.
 */
final class MessageService
{
    /**
     * List messages for the conversation, newest first.
     *
     * @return CursorPaginator<int, Message>
     */
    public function listForConversation(Conversation $conversation): CursorPaginator
    {
        return $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->cursorPaginate(30);
    }

    /**
     * Persist a message and broadcast it to the conversation channel.
     *
     * The database write and conversation timestamp update happen in a single
     * transaction. A failed broadcast is logged but does not fail the request.
     */
    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $message = DB::transaction(function () use ($conversation, $sender, $body): Message {
            $message = $conversation->messages()->create([
                'sender_id' => $sender->id,
                'body' => trim($body),
            ]);

            $conversation->touch();

            return $message;
        });

        $message->load('sender');

        Log::info('message.sent', [
            'message_id' => $message->id,
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'body_length' => mb_strlen($message->body),
        ]);

        try {
            MessageSent::dispatch($message);
        } catch (Throwable $exception) {
            Log::warning('message.broadcast_failed', [
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'exception' => $exception,
            ]);
        }

        return $message;
    }
}
