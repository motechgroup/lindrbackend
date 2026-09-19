<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Determine whether the user can view or interact with the conversation.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }

    /**
     * Determine whether the user can send messages in the conversation.
     */
    public function sendMessage(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user->id);
    }
}
