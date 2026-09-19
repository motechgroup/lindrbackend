<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\StartConversationRequest;
use App\Http\Resources\ConversationResource;
use App\Models\User;
use App\Services\ChatService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    use ApiResponse;

    public function __construct(public ChatService $chatService) {}

    public function index(Request $request): JsonResponse
    {
        $conversations = $this->chatService->getUserConversations($request->user());

        return $this->successResponse(
            ConversationResource::collection($conversations),
            'Conversations retrieved.'
        );
    }

    public function store(StartConversationRequest $request): JsonResponse
    {
        $recipient = User::findOrFail($request->validated()['recipient_id']);
        $conversation = $this->chatService->getOrCreateConversation($request->user(), $recipient);

        $conversation->load(['latestMessage', 'participants.user.profile', 'participants.user.photos']);

        return $this->successResponse(
            new ConversationResource($conversation),
            'Conversation created or retrieved.',
            201
        );
    }
}
