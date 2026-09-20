<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the authenticated user can view another user's profile.
     */
    public function view(User $authUser, User $targetUser): bool
    {
        if ($authUser->isAdmin()) {
            return true;
        }

        // Suspended or non-active target users cannot be viewed
        return $targetUser->isActive();
    }

    /**
     * Determine whether the authenticated user can update the target user profile.
     */
    public function update(User $authUser, User $targetUser): bool
    {
        return $authUser->id === $targetUser->id || $authUser->isAdmin();
    }

    /**
     * Determine whether the authenticated user can perform admin actions.
     */
    public function adminAction(User $authUser): bool
    {
        return $authUser->isAdmin();
    }

    /**
     * Determine whether the authenticated user can manage creator verification.
     */
    public function manageCreatorVerification(User $authUser, ?User $targetUser = null): bool
    {
        return $authUser->isAdmin() && $authUser->isActive();
    }
}
