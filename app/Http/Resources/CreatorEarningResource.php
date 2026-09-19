<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreatorEarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->source_type,
            'gross_coins' => $this->gross_coins,
            'recipient_share_percentage' => (float) $this->recipient_share_percentage,
            'recipient_earnings_amount' => (float) $this->recipient_earnings_amount,
            'status' => $this->status?->value ?? (string) $this->status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
