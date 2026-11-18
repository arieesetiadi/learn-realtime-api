<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\StoreConversationRequest;
use App\Http\Resources\API\V1\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    /**
     * List the conversations the authenticated user participates in.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $conversations = Conversation::query()
            ->forUser($request->user()->id)
            ->with(['userOne', 'userTwo', 'latestMessage.sender'])
            ->latest('updated_at')
            ->cursorPaginate(20);

        return ConversationResource::collection($conversations);
    }

    /**
     * Find or create a conversation with the given recipient.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = Conversation::betweenUsers(
            $request->user()->id,
            (int) $request->validated('recipient_id'),
        );

        $conversation->load(['userOne', 'userTwo', 'latestMessage.sender']);

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }
}
