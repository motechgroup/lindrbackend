<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        $presenceStatus = $this->online_status ?? 'offline';
        $isAvailable = $presenceStatus === 'available' && ($this->last_heartbeat_at === null || $this->last_heartbeat_at->gt(now()->subMinutes(2)));

        $user = $this->user;
        $viewer = $request->user();

        $interestNames = [];
        if ($user) {
            $user->loadMissing('interests');
            if ($user->interests->count() > 0) {
                $interestNames = $user->interests->pluck('name')->toArray();
            }
        }
        if (empty($interestNames) && ! empty($this->interests) && is_array($this->interests)) {
            $interestNames = $this->interests;
        }

        $sharedInterests = [];
        if ($viewer && $user && $viewer->id !== $user->id) {
            $viewer->loadMissing('interests');
            $viewerInterests = $viewer->interests->pluck('name')->toArray();
            if (empty($viewerInterests) && ! empty($viewer->profile?->interests)) {
                $viewerInterests = (array) $viewer->profile->interests;
            }
            $sharedInterests = array_values(array_intersect($viewerInterests, $interestNames));
        }

        $completenessScore = $user ? $user->getCompletenessScore() : 0;

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'display_name' => $this->display_name,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'age' => $this->date_of_birth?->age,
            'gender' => $this->gender,
            'bio' => $this->bio,
            'city' => $this->city,
            'country' => $this->country,
            'country_code' => $this->country_code,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'interests' => $interestNames,
            'shared_interests' => $sharedInterests,
            'completeness_score' => $completenessScore,
            'online_status' => $presenceStatus,
            'presence_status' => $presenceStatus,
            'is_available_for_call' => $isAvailable,
            'last_heartbeat_at' => $this->last_heartbeat_at?->toIso8601String(),
            'is_verified' => (bool) ($this->is_verified && $user && $user->is_creator && in_array($user->creator_status, ['approved', 'verified'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
