<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Carbon\Carbon;

class CreatorEligibilityService
{
    /**
     * Determine if a user is eligible to earn creator credits from interactions.
     * Gender-neutral: Both Men and Women can be verified creators.
     */
    public function canEarnCredits(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $statusVal = $user->status instanceof UserStatus ? $user->status->value : (string) ($user->status ?? 'active');
        $isActive = ($statusVal === 'active');
        $isVerifiedCreator = (bool) $user->is_creator && in_array($user->creator_status, ['approved', 'verified']);

        return $isActive && $isVerifiedCreator;
    }

    /**
     * Get list of eligibility issues preventing creator activation.
     */
    public function getEligibilityIssues(?User $user): array
    {
        if (! $user) {
            return ['User account not found.'];
        }

        $issues = [];

        $statusVal = $user->status instanceof UserStatus ? $user->status->value : (string) ($user->status ?? 'active');
        if ($statusVal !== 'active') {
            $issues[] = 'User account is inactive, suspended, or banned.';
        }

        if (! $user->is_creator || ! in_array($user->creator_status, ['approved', 'verified'])) {
            $issues[] = 'User creator application must be approved and verified.';
        }

        if ($user->profile?->date_of_birth) {
            $age = Carbon::parse($user->profile->date_of_birth)->age;
            if ($age < 18) {
                $issues[] = 'User must be at least 18 years old to become a creator.';
            }
        }

        return $issues;
    }

    /**
     * Determine if a creator is eligible to request withdrawals.
     */
    public function canWithdraw(?User $user, int $credits = 0): array
    {
        if (! $user) {
            return ['allowed' => false, 'reason' => 'User not found.'];
        }

        $statusVal = $user->status instanceof UserStatus ? $user->status->value : (string) ($user->status ?? 'active');
        if ($statusVal !== 'active') {
            return ['allowed' => false, 'reason' => 'Account is currently inactive or suspended.'];
        }

        if (! $user->is_creator || ! in_array($user->creator_status, ['approved', 'verified'])) {
            return ['allowed' => false, 'reason' => 'User is not an approved verified creator.'];
        }

        if (! $user->mpesa_phone_verified || empty($user->mpesa_phone)) {
            return ['allowed' => false, 'reason' => 'Creator M-Pesa phone number must be verified via OTP prior to withdrawal.'];
        }

        if (method_exists($user, 'hasActivePayoutHold') && $user->hasActivePayoutHold()) {
            return ['allowed' => false, 'reason' => 'Withdrawals are currently locked due to an active payout security hold.'];
        }

        $hasPending = Withdrawal::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('female_user_id', $user->id);
        })->whereIn('status', ['pending', 'processing', WithdrawalStatus::Pending, WithdrawalStatus::Processing])->exists();

        if ($hasPending) {
            return ['allowed' => false, 'reason' => 'You already have an active pending or processing withdrawal request. Please wait for it to complete.'];
        }

        $minCredits = (int) PlatformSetting::get('minimum_withdrawal_credits', 100);
        if ($credits > 0 && $credits < $minCredits) {
            return ['allowed' => false, 'reason' => "Minimum withdrawal is {$minCredits} credits."];
        }

        $availableCredits = (int) (Wallet::where('user_id', $user->id)->value('credits') ?? 0);
        if ($credits > 0 && $credits > $availableCredits) {
            return ['allowed' => false, 'reason' => 'Insufficient creator credit balance for payout request.'];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Alias for backward compatibility.
     */
    public function canWithdrawPayout(?User $user): bool
    {
        $res = $this->canWithdraw($user);

        return $res['allowed'];
    }
}
