<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoinPurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'package_id' => $this->package_id,
            'package_name' => $this->package?->name,
            'amount_kes' => (float) $this->amount_kes,
            'coins_credited' => $this->coins_credited,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'phone_number' => $this->phone_number,
            'checkout_request_id' => $this->checkout_request_id,
            'mpesa_receipt_number' => $this->mpesa_receipt_number,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
