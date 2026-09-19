<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $isSelf = ($viewer && $viewer->id === $this->id)
            || $request->is('*auth/login*')
            || $request->is('*auth/register*')
            || $request->is('*auth/google*')
            || $request->is('*auth/me*');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->when($isSelf, $this->email),
            'phone' => $this->when($isSelf, $this->phone),
            'avatar' => $this->avatar,
            'role' => $this->role?->value ?? (string) $this->role,
            'status' => $this->status?->value ?? (string) $this->status,
            'is_onboarded' => $this->isOnboarded(),
            'is_creator' => (bool) $this->is_creator,
            'creator_status' => $this->creator_status ?? 'none',
            'email_verified' => $this->when($isSelf, $this->email_verified_at !== null),
            'phone_verified' => $this->when($isSelf, $this->phone_verified_at !== null),
            'liveness_verified' => $this->liveness_verified_at !== null,
            'mpesa_phone_verified' => $this->when($isSelf, (bool) $this->mpesa_phone_verified),
            'has_payout_hold' => $this->when($isSelf, $this->hasActivePayoutHold()),
            'profile' => new UserProfileResource($this->whenLoaded('profile')),
            'photos' => UserPhotoResource::collection($this->whenLoaded('photos')),
            'wallet_balance' => $this->when($isSelf, fn () => $this->whenLoaded('wallet', fn () => $this->wallet->coin_balance)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
