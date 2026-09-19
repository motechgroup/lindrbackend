<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserMatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        $otherUser = $this->getOtherUser($currentUserId ?? 0);

        return [
            'id' => $this->id,
            'user_low_id' => $this->user_low_id,
            'user_high_id' => $this->user_high_id,
            'matched_at' => $this->matched_at?->toIso8601String(),
            'other_user' => $otherUser ? new UserResource($otherUser) : null,
        ];
    }
}
