<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserReport;

class ReportService
{
    public function reportUser(User $reporter, User $reported, string $reason, ?string $description = null): UserReport
    {
        if ($reporter->id === $reported->id) {
            throw new \InvalidArgumentException('You cannot report yourself.');
        }

        return UserReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'reason' => $reason,
            'description' => $description,
            'status' => 'pending',
        ]);
    }
}
