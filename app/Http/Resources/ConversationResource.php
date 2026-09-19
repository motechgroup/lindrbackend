<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id ?? 0;
        $otherUser = $this->getOtherParticipant($currentUserId);

        return [
            'id' => $this->id,
            'other_user' => $otherUser ? new UserResource($otherUser) : null,
            'latest_message' => $this->whenLoaded('latestMessage', fn () => new MessageResource($this->latestMessage)),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
