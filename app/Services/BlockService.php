<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserBlock;

class BlockService
{
    public function blockUser(User $blocker, User $blocked): UserBlock
    {
        if ($blocker->id === $blocked->id) {
            throw new \InvalidArgumentException('You cannot block yourself.');
        }

        return UserBlock::firstOrCreate([
            'blocker_id' => $blocker->id,
            'blocked_id' => $blocked->id,
        ]);
    }

    public function unblockUser(User $blocker, User $blocked): bool
    {
        return (bool) UserBlock::where('blocker_id', $blocker->id)
            ->where('blocked_id', $blocked->id)
            ->delete();
    }

    public function getBlockedUserIds(User $user): array
    {
        return UserBlock::where('blocker_id', $user->id)
            ->orWhere('blocked_id', $user->id)
            ->pluck('blocked_id')
            ->merge(
                UserBlock::where('blocked_id', $user->id)->pluck('blocker_id')
            )
            ->unique()
            ->toArray();
    }
}
