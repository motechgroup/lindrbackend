<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinPackageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'token_amount' => $this->total_coins,
            'coin_amount' => $this->coin_amount,
            'bonus_coins' => $this->bonus_coins,
            'total_coins' => $this->total_coins,
            'price' => (float) $this->price_kes,
            'price_kes' => (float) $this->price_kes,
            'currency' => 'KES',
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->display_order,
            'display_order' => $this->display_order,
        ];
    }
}
