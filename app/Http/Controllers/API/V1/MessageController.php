<?php

namespace App\Http\Controllers\API\V1;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\StoreMessageRequest;
use App\Http\Resources\API\V1\MessageResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    /**
     * List the messages in the conversation, newest first.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        $messages = $conversation->messages()
            ->with('sender')
            ->latest('id')
            ->cursorPaginate(30);

        return MessageResource::collection($messages);
    }

    /**
     * Send a message in the conversation.
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => trim($request->validated('body')),
        ]);

        $conversation->touch();

        $message->load('sender');

        MessageSent::dispatch($message);

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
