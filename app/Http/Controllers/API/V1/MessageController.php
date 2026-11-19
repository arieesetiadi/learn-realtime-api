<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\StoreMessageRequest;
use App\Http\Resources\API\V1\MessageResource;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class MessageController extends Controller
{
    public function __construct(
        private readonly MessageService $messages,
    ) {}

    /**
     * GET /api/v1/conversations/{conversation}/messages
     *
     * List the messages in the conversation, newest first.
     */
    public function index(Request $request, Conversation $conversation): AnonymousResourceCollection
    {
        Gate::authorize('view', $conversation);

        return MessageResource::collection(
            $this->messages->listForConversation($conversation),
        );
    }

    /**
     * POST /api/v1/conversations/{conversation}/messages
     *
     * Send a message in the conversation.
     */
    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        Gate::authorize('view', $conversation);

        /** @var User $user */
        $user = $request->user();

        $message = $this->messages->send(
            $conversation,
            $user,
            (string) $request->validated('body'),
        );

        return (new MessageResource($message))
            ->response()
            ->setStatusCode(201);
    }
}
