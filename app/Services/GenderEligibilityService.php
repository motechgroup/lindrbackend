<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class GenderEligibilityService
{
    /**
     * Determine the strict opposite target gender string for a given user.
     */
    public function getTargetGender(User $user): ?string
    {
        $role = $user->role;
        $profileGender = strtolower($user->profile?->gender ?? '');

        if ($role === UserRole::Male || $profileGender === 'male') {
            return 'female';
        }

        if ($role === UserRole::Female || $profileGender === 'female') {
            return 'male';
        }

        return null;
    }

    /**
     * Determine if a candidate user is of the eligible opposite gender for the viewer.
     */
    public function isGenderEligible(User $viewer, User $candidate): bool
    {
        if ($viewer->id === $candidate->id) {
            return false;
        }

        $targetGender = $this->getTargetGender($viewer);
        if (! $targetGender) {
            return false;
        }

        $candidateGender = strtolower($candidate->profile?->gender ?? '');
        $candidateRole = $candidate->role;

        if ($targetGender === 'female') {
            return $candidateRole === UserRole::Female || $candidateGender === 'female';
        }

        if ($targetGender === 'male') {
            return $candidateRole === UserRole::Male || $candidateGender === 'male';
        }

        return false;
    }

    /**
     * Apply strict gender eligibility filtering to a Eloquent query builder.
     */
    public function applyGenderFilter(Builder $query, User $viewer): Builder
    {
        $targetGender = $this->getTargetGender($viewer);

        if ($targetGender === 'female') {
            return $query->where(function ($q) {
                $q->where('role', UserRole::Female)
                    ->orWhereHas('profile', function ($pq) {
                        $pq->where('gender', 'female');
                    });
            });
        }

        if ($targetGender === 'male') {
            return $query->where(function ($q) {
                $q->where('role', UserRole::Male)
                    ->orWhereHas('profile', function ($pq) {
                        $pq->where('gender', 'male');
                    });
            });
        }

        // Default: if viewer gender is undefined, filter to empty set for safety
        return $query->whereRaw('1 = 0');
    }

    /**
     * Verify if viewer can discover candidate.
     */
    public function canDiscover(User $viewer, User $candidate): bool
    {
        return $this->isGenderEligible($viewer, $candidate);
    }

    /**
     * Verify if initiator can match with candidate.
     */
    public function canMatch(User $initiator, User $candidate): bool
    {
        return $this->isGenderEligible($initiator, $candidate);
    }

    /**
     * Verify if caller can initiate a call with recipient.
     */
    public function canCall(User $caller, User $recipient): bool
    {
        return $this->isGenderEligible($caller, $recipient);
    }
}
