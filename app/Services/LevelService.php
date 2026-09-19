<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\CoinPurchase;
use App\Models\LevelPenalty;
use App\Models\LevelRule;
use App\Models\PaymentTransaction;
use App\Models\SpotlightPurchase;
use App\Models\User;
use App\Models\UserLevelHistory;

class LevelService
{
    public function __construct(public ?NotificationService $notificationService = null) {}

    /**
     * Get default level rules if database table is empty.
     */
    public function seedDefaultRulesIfEmpty(): void
    {
        if (LevelRule::count() === 0) {
            $defaults = [
                ['level' => 0, 'name' => 'Level 0', 'threshold_score' => 0, 'exposure_multiplier' => 1.0, 'creator_commission_pct' => 60.0, 'benefits' => ['Baseline discover exposure', 'Standard earnings share']],
                ['level' => 1, 'name' => 'Level 1', 'threshold_score' => 100, 'exposure_multiplier' => 1.2, 'creator_commission_pct' => 62.0, 'benefits' => ['+20% Discover boost', '62% Creator commission']],
                ['level' => 2, 'name' => 'Level 2', 'threshold_score' => 300, 'exposure_multiplier' => 1.5, 'creator_commission_pct' => 65.0, 'benefits' => ['+50% Discover boost', '65% Creator commission', 'Priority support']],
                ['level' => 3, 'name' => 'Level 3', 'threshold_score' => 600, 'exposure_multiplier' => 1.8, 'creator_commission_pct' => 70.0, 'benefits' => ['+80% Discover boost', '70% Creator commission', 'Featured tag']],
                ['level' => 4, 'name' => 'Level 4', 'threshold_score' => 1000, 'exposure_multiplier' => 2.2, 'creator_commission_pct' => 75.0, 'benefits' => ['+120% Premium exposure', '75% Creator commission', 'VIP profile badge']],
                ['level' => 5, 'name' => 'Level 5', 'threshold_score' => 1500, 'exposure_multiplier' => 3.0, 'creator_commission_pct' => 80.0, 'benefits' => ['Max 3.0x Discover priority', '80% Creator commission', 'Top Creator status']],
            ];

            foreach ($defaults as $rule) {
                LevelRule::create($rule);
            }
        }
    }

    /**
     * Expose authoritative historical topup metrics for Level engine.
     * Only successful top-ups contribute.
     *
     * @return array<string, mixed>
     */
    public function getTopupMetrics(User $user): array
    {
        $successfulTxs = PaymentTransaction::where('user_id', $user->id)
            ->where('status', 'successful')
            ->get();

        $legacyPurchases = CoinPurchase::where('user_id', $user->id)
            ->whereIn('status', ['successful', 'completed'])
            ->get();

        $txCount = $successfulTxs->count() + $legacyPurchases->count();
        $tokenSum = (int) $successfulTxs->sum('expected_coins') + (int) $legacyPurchases->sum('coins_credited');
        $amountSum = (float) $successfulTxs->sum('amount') + (float) $legacyPurchases->sum('amount_kes');

        $firstTxDate = $successfulTxs->min('created_at') ?? $legacyPurchases->min('created_at');
        $lastTxDate = $successfulTxs->max('created_at') ?? $legacyPurchases->max('created_at');

        return [
            'total_successful_purchases' => $txCount,
            'total_token_amount_purchased' => $tokenSum,
            'total_amount_spent' => $amountSum,
            'first_topup_at' => $firstTxDate?->toIso8601String(),
            'last_topup_at' => $lastTxDate?->toIso8601String(),
        ];
    }

    /**
     * Calculate authoritative user level data server-side.
     *
     * @return array<string, mixed>
     */
    public function getUserLevelData(User $user): array
    {
        $this->seedDefaultRulesIfEmpty();

        // 1. Calculate Spotlight contribution (1 token spent = 1 point)
        $spotlightPoints = (int) SpotlightPurchase::where('user_id', $user->id)->sum('tokens_spent');

        // 2. Calculate Top-up contribution (1 token purchased = 1 point)
        $metrics = $this->getTopupMetrics($user);
        $topupPoints = $metrics['total_token_amount_purchased'];

        // 3. Calculate Membership duration (1 day = 5 points)
        $createdAt = $user->created_at ?? now();
        $daysMember = (int) max(0, (int) $createdAt->diffInDays(now()));
        $membershipPoints = $daysMember * 5;

        // 4. Calculate confirmed moderation penalties
        $penaltyPoints = (int) LevelPenalty::where('user_id', $user->id)->sum('score_penalty');

        // 5. Total gross score
        $grossScore = max(0, $spotlightPoints + $topupPoints + $membershipPoints - $penaltyPoints);

        // Fetch active level rules ordered by level ascending
        $rules = LevelRule::where('is_active', true)->orderBy('level', 'asc')->get();

        $currentRule = $rules->first();
        $nextRule = null;

        foreach ($rules as $rule) {
            if ($grossScore >= $rule->threshold_score) {
                $currentRule = $rule;
            } else {
                $nextRule = $rule;
                break;
            }
        }

        // Calculate progress percentage to next level
        $progressPct = 100;
        $pointsToNextLevel = 0;

        if ($nextRule) {
            $currentThreshold = $currentRule->threshold_score;
            $nextThreshold = $nextRule->threshold_score;
            $range = max(1, $nextThreshold - $currentThreshold);
            $earnedInRange = max(0, $grossScore - $currentThreshold);

            $progressPct = (int) min(100, round(($earnedInRange / $range) * 100));
            $pointsToNextLevel = max(0, $nextThreshold - $grossScore);
        }

        // Determine community standing
        $standing = 'GOOD_STANDING';
        if ($user->status !== UserStatus::Active) {
            $standing = 'RESTRICTED';
        } elseif ($penaltyPoints > 0) {
            $standing = 'WARNING';
        }

        return [
            'level' => $currentRule->level,
            'level_name' => $currentRule->name,
            'score' => $grossScore,
            'next_level' => $nextRule?->level,
            'next_threshold' => $nextRule?->threshold_score,
            'points_to_next_level' => $pointsToNextLevel,
            'progress_pct' => $progressPct,
            'exposure_multiplier' => $currentRule->exposure_multiplier,
            'creator_commission_pct' => $currentRule->creator_commission_pct,
            'benefits' => $currentRule->benefits ?? [],
            'community_standing' => $standing,
            'membership_days' => $daysMember,
            'breakdown' => [
                'spotlight_points' => $spotlightPoints,
                'topup_points' => $topupPoints,
                'membership_points' => $membershipPoints,
                'penalties' => $penaltyPoints,
            ],
            'topup_metrics' => $metrics,
        ];
    }

    /**
     * Recalculate and record level state changes to immutable user_level_histories audit log.
     *
     * @return array<string, mixed>
     */
    public function recalculateUserLevel(User $user, string $reason = 'RECALCULATION', ?int $adminId = null): array
    {
        $lastHistory = UserLevelHistory::where('user_id', $user->id)->latest()->first();
        $prevLevel = $lastHistory ? $lastHistory->new_level : 0;
        $prevScore = $lastHistory ? $lastHistory->new_score : 0;

        $levelData = $this->getUserLevelData($user);
        $newLevel = (int) $levelData['level'];
        $newScore = (int) $levelData['score'];

        UserLevelHistory::create([
            'user_id' => $user->id,
            'previous_level' => $prevLevel,
            'new_level' => $newLevel,
            'previous_score' => $prevScore,
            'new_score' => $newScore,
            'reason' => $reason,
            'admin_id' => $adminId,
        ]);

        if ($newLevel > $prevLevel && $this->notificationService) {
            $this->notificationService->sendLevelUpNotification(
                $user,
                $newLevel,
                (string) ($levelData['level_name'] ?? "Level {$newLevel}")
            );
        }

        return $levelData;
    }
}
