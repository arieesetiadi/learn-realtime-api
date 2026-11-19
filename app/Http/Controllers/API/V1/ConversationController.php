<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\StoreConversationRequest;
use App\Http\Resources\API\V1\ConversationResource;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
    ) {}

    /**
     * GET /api/v1/conversations
     *
     * List the conversations the authenticated user participates in.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return ConversationResource::collection(
            $this->conversations->listForUser($user->id),
        );
    }

    /**
     * POST /api/v1/conversations
     *
     * Find or create a conversation with the given recipient.
     *
     * Returns 201 when a new conversation is created and 200 when the
     * conversation already existed.
     */
    public function store(StoreConversationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $conversation = $this->conversations->findOrCreateBetween(
            $user->id,
            (int) $request->validated('recipient_id'),
        );

        return (new ConversationResource($conversation))
            ->response()
            ->setStatusCode($conversation->wasRecentlyCreated ? 201 : 200);
    }
}
