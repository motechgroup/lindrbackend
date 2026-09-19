<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;

class DiscoverRankingService
{
    public function __construct(
        public LevelService $levelService,
        public SpotlightService $spotlightService,
        public PresenceService $presenceService
    ) {}

    /**
     * Calculate total discovery ranking score for a user.
     * Returns 0.0 if user is ineligible (banned, suspended, or offline).
     */
    public function calculateRankingScore(User $user, ?User $viewer = null): float
    {
        // 1. Account status eligibility check
        if ($user->status !== UserStatus::Active) {
            return 0.0;
        }

        // 2. Presence check
        $presence = $this->presenceService->getUserPresence($user);
        $status = $presence['status'] ?? 'offline';

        if ($status === 'offline') {
            return 0.0;
        }

        // Base profile score
        $score = 100.0;

        // Presence relevance score (+50 for available, +10 for busy)
        $presenceBonus = match ($status) {
            'available' => 50.0,
            'busy' => 10.0,
            default => 0.0,
        };
        $score += $presenceBonus;

        // Profile Completeness Contribution (up to +30 pts)
        $completenessScore = $user->getCompletenessScore();
        $completenessBonus = ($completenessScore / 100.0) * 30.0;
        $score += $completenessBonus;

        // Level contribution (exposure_multiplier)
        $levelData = $this->levelService->getUserLevelData($user);
        $exposureMult = (float) ($levelData['exposure_multiplier'] ?? 1.0);
        $levelBonus = 100.0 * max(0.0, $exposureMult - 1.0);
        $score += $levelBonus;

        // Spotlight contribution (boost_multiplier)
        $spotlight = $this->spotlightService->getActiveSpotlight($user);
        if ($spotlight && $spotlight->package) {
            $boostMult = (float) $spotlight->package->boost_multiplier;
            $spotlightBonus = 100.0 * max(0.0, $boostMult - 1.0);
            $score += $spotlightBonus;
        }

        // Activity / Freshness bonus
        $lastHeartbeat = $user->profile?->last_heartbeat_at;
        if ($lastHeartbeat) {
            if ($lastHeartbeat->gt(now()->subMinutes(15))) {
                $score += 20.0;
            } elseif ($lastHeartbeat->gt(now()->subHour())) {
                $score += 10.0;
            }
        }

        // Controlled rotation / Seeded randomization (0 to 14 pts based on hourly seed)
        $timeBucket = date('Y-m-d-H');
        $seed = sprintf('%s-%s', $user->id, $timeBucket);
        $rotationNoise = (float) (abs(crc32($seed)) % 15);
        $score += $rotationNoise;

        return round($score, 2);
    }
}
