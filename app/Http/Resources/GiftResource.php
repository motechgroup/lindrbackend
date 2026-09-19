<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => $this->image_url,
            'animation_reference' => $this->animation_reference,
            'coin_price' => $this->coin_price,
            'recipient_share_percentage' => (float) $this->recipient_share_percentage,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
