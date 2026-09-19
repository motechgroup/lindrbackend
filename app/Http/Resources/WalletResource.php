<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'balance' => $this->coin_balance,
            'available_balance' => $this->coin_balance,
            'coin_balance' => $this->coin_balance,
            'currency' => 'TOKENS',
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
