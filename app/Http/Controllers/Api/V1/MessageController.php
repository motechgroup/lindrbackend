<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chat\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Services\ChatService;
use App\Services\PaidMessagingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    use ApiResponse;

    public function __construct(
        public ChatService $chatService,
        public PaidMessagingService $paidMessagingService
    ) {}

    public function index(Request $request, Conversation $conversation): JsonResponse
    {
        $messages = $this->chatService->getMessages(
            $request->user(),
            $conversation,
            (int) $request->input('per_page', 30)
        );

        return $this->paginatedResponse(
            MessageResource::collection($messages),
            'Messages retrieved successfully.'
        );
    }

    public function store(SendMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if ($this->paidMessagingService && $user->isMale()) {
            $message = $this->paidMessagingService->sendPaidMessage(
                $user,
                $conversation,
                $validated['content'],
                $validated['type'] ?? 'text'
            );
        } else {
            $message = $this->chatService->sendMessage(
                $user,
                $conversation,
                $validated['content'],
                $validated['type'] ?? 'text',
                false
            );
        }

        return $this->successResponse(
            new MessageResource($message),
            'Message sent successfully.',
            201
        );
    }
}
