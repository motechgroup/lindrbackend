<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'recipient_id' => $this->recipient_id,
            'gift_id' => $this->gift_id,
            'gift' => [
                'id' => $this->gift_id,
                'name' => $this->gift?->name,
                'image_url' => $this->gift?->image_url,
                'animation_reference' => $this->gift?->animation_reference,
                'coin_price' => $this->coin_price,
            ],
            'sender' => [
                'id' => $this->sender_id,
                'name' => $this->sender?->name,
            ],
            'recipient' => [
                'id' => $this->recipient_id,
                'name' => $this->recipient?->name,
            ],
            'gift_name' => $this->gift?->name,
            'coin_price' => $this->coin_price,
            'tokens_spent' => $this->coin_price,
            'recipient_earnings_amount' => (float) $this->recipient_earnings_amount,
            'credits_awarded' => (float) $this->recipient_earnings_amount,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
