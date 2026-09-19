<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rawPhone = (string) ($this->mpesa_number ?? '');
        $maskedPhone = strlen($rawPhone) >= 8
            ? substr($rawPhone, 0, 4).'****'.substr($rawPhone, -4)
            : $rawPhone;

        return [
            'id' => (string) $this->id,
            'user_id' => $this->user_id ?? $this->female_user_id,
            'credits_deducted' => (int) ($this->credits_deducted ?? 0),
            'amount_credits' => (int) ($this->credits_deducted ?? 0),
            'conversion_rate' => (float) ($this->conversion_rate ?? 10.0),
            'amount_kes' => (float) $this->amount_kes,
            'cash_amount_usd' => (float) ($this->cash_amount_usd ?? round($this->amount_kes / 130.0, 2)),
            'mpesa_number' => $rawPhone,
            'masked_mpesa_number' => $maskedPhone,
            'method' => $this->method ?? 'mpesa',
            'status' => $this->status?->value ?? (string) $this->status,
            'provider_reference' => $this->provider_reference,
            'admin_notes' => $this->admin_notes,
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'processed_at' => $this->processed_at?->toIso8601String(),
        ];
    }
}
