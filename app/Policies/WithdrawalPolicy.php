<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Withdrawal;

class WithdrawalPolicy
{
    public function create(User $user): bool
    {
        return $user->isFemale() && $user->isActive();
    }

    public function view(User $user, Withdrawal $withdrawal): bool
    {
        return $user->id === $withdrawal->female_user_id || $user->isAdmin();
    }
}
