<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ChatService
{
    public function __construct(
        public SafetyService $safetyService,
        public NotificationService $notificationService
    ) {}

    /**
     * Find existing conversation between two users or create a new one.
     */
    public function getOrCreateConversation(User $user1, User $user2): Conversation
    {
        if ($user1->id === $user2->id) {
            throw new \InvalidArgumentException('Cannot create conversation with yourself.');
        }

        $safetyCheck = $this->safetyService->canInteract($user1, $user2);
        if (! $safetyCheck['allowed']) {
            throw new AccessDeniedHttpException($safetyCheck['reason']);
        }

        $existingId = ConversationParticipant::where('user_id', $user1->id)
            ->whereIn('conversation_id', function ($query) use ($user2) {
                $query->select('conversation_id')
                    ->from('conversation_participants')
                    ->where('user_id', $user2->id);
            })
            ->value('conversation_id');

        if ($existingId) {
            return Conversation::findOrFail($existingId);
        }

        return DB::transaction(function () use ($user1, $user2) {
            $conversation = Conversation::create();

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user1->id,
            ]);

            ConversationParticipant::create([
                'conversation_id' => $conversation->id,
                'user_id' => $user2->id,
            ]);

            return $conversation;
        });
    }

    /**
     * Get all conversations for a user.
     */
    public function getUserConversations(User $user)
    {
        return Conversation::whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->with(['latestMessage', 'participants.user.profile', 'participants.user.photos'])
            ->latest('updated_at')
            ->get();
    }

    /**
     * Get paginated messages for a conversation.
     */
    public function getMessages(User $user, Conversation $conversation, int $perPage = 30): LengthAwarePaginator
    {
        if (! $conversation->isParticipant($user->id)) {
            throw new AccessDeniedHttpException('Unauthorized to view messages for this conversation.');
        }

        $this->markAsRead($user, $conversation);

        return Message::where('conversation_id', $conversation->id)
            ->with(['sender.profile', 'recipient.profile'])
            ->latest('created_at')
            ->paginate($perPage);
    }

    /**
     * Persist a message in a conversation.
     */
    public function sendMessage(User $sender, Conversation $conversation, string $content, string $type = 'text', bool $isPaid = false): Message
    {
        if (! $conversation->isParticipant($sender->id)) {
            throw new \InvalidArgumentException('Sender is not a participant in this conversation.');
        }

        $recipient = $conversation->getOtherParticipant($sender->id);
        if (! $recipient) {
            throw new \InvalidArgumentException('Recipient participant not found.');
        }

        return DB::transaction(function () use ($sender, $recipient, $conversation, $content, $type, $isPaid) {
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'content' => $content,
                'type' => $type,
                'is_paid' => $isPaid,
                'is_read' => false,
            ]);

            $conversation->touch();

            // Send NEW_MESSAGE notification to recipient
            $this->notificationService->notifyNewMessage($recipient, $sender, $content, $conversation->id);

            return $message;
        });
    }

    /**
     * Mark all unread messages in conversation as read for the user.
     */
    public function markAsRead(User $user, Conversation $conversation): void
    {
        Message::where('conversation_id', $conversation->id)
            ->where('recipient_id', $user->id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        ConversationParticipant::where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }
}
