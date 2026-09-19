<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserBlock;

class SafetyService
{
    public function __construct(
        public GenderEligibilityService $genderEligibilityService
    ) {}

    /**
     * Determine if two users can interact (messaging, calls, gifts, general social actions).
     *
     * @return array{allowed: bool, reason?: string, error_code?: string}
     */
    public function canInteract(User $userA, User $userB): array
    {
        if ($userA->id === $userB->id) {
            return [
                'allowed' => false,
                'reason' => 'Self interaction is not permitted.',
                'error_code' => 'INVALID_TARGET',
            ];
        }

        $statusA = $userA->status instanceof UserStatus ? $userA->status->value : (string) $userA->status;
        if ($statusA !== UserStatus::Active->value) {
            $code = $statusA === UserStatus::Banned->value ? 'ACCOUNT_BANNED' : 'ACCOUNT_SUSPENDED';

            return [
                'allowed' => false,
                'reason' => 'Your account is currently restricted.',
                'error_code' => $code,
            ];
        }

        $statusB = $userB->status instanceof UserStatus ? $userB->status->value : (string) $userB->status;
        if ($statusB !== UserStatus::Active->value) {
            $code = match ($statusB) {
                UserStatus::Banned->value => 'ACCOUNT_BANNED',
                UserStatus::Suspended->value => 'ACCOUNT_SUSPENDED',
                default => 'RECIPIENT_UNAVAILABLE',
            };

            return [
                'allowed' => false,
                'reason' => "ACCOUNT_SUSPENDED: The recipient account is currently {$statusB}.",
                'error_code' => $code,
            ];
        }

        $isBlocked = UserBlock::where(function ($q) use ($userA, $userB) {
            $q->where('blocker_id', $userA->id)->where('blocked_id', $userB->id);
        })->orWhere(function ($q) use ($userA, $userB) {
            $q->where('blocker_id', $userB->id)->where('blocked_id', $userA->id);
        })->exists();

        if ($isBlocked) {
            return [
                'allowed' => false,
                'reason' => 'Interaction is blocked between these accounts.',
                'error_code' => 'USER_BLOCKED',
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Determine if a candidate profile can appear in a viewer's Discover feed.
     */
    public function canDiscover(User $viewer, User $candidate): bool
    {
        if ($viewer->id === $candidate->id) {
            return false;
        }

        // Opposite gender check for Discover
        if (! $this->genderEligibilityService->isGenderEligible($viewer, $candidate)) {
            return false;
        }

        $check = $this->canInteract($viewer, $candidate);

        return $check['allowed'];
    }

    /**
     * Determine if a candidate profile can be matched with a caller.
     */
    public function canMatch(User $caller, User $candidate): bool
    {
        if (! $this->canDiscover($caller, $candidate)) {
            return false;
        }

        $presence = $candidate->profile?->presence_status ?? 'online';
        if (in_array($presence, ['busy', 'offline'])) {
            return false;
        }

        return true;
    }

    /**
     * Determine if caller can initiate a direct video call to receiver.
     *
     * @return array{allowed: bool, reason?: string, error_code?: string}
     */
    public function canCall(User $caller, User $receiver): array
    {
        // Require opposite gender for direct video calls
        if (! $this->genderEligibilityService->canCall($caller, $receiver)) {
            return [
                'allowed' => false,
                'reason' => 'Video calls are restricted to opposite-gender pairs.',
                'error_code' => 'GENDER_INELIGIBLE',
            ];
        }

        return $this->canInteract($caller, $receiver);
    }

    /**
     * Determine if sender can send a message to receiver.
     *
     * @return array{allowed: bool, reason?: string, error_code?: string}
     */
    public function canMessage(User $sender, User $receiver): array
    {
        return $this->canInteract($sender, $receiver);
    }

    /**
     * Determine if sender can send a gift to receiver.
     *
     * @return array{allowed: bool, reason?: string, error_code?: string}
     */
    public function canSendGift(User $sender, User $receiver): array
    {
        return $this->canInteract($sender, $receiver);
    }
}
