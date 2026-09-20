<?php

namespace App\Services;

use App\Models\Gift;
use App\Models\PlatformSetting;
use App\Models\User;

class MonetizationService
{
    public function __construct(public ?LevelService $levelService = null) {}

    /**
     * Unified calculation helper for creator splits.
     *
     * @return array{gross_tokens: int, creator_amount: int, platform_amount: int, creator_share_pct: float}
     */
    public function calculateSplit(
        int|float $tokenCost,
        string $type = 'chat',
        string $gender = 'female',
        ?User $creator = null,
        ?float $overridePct = null
    ): array {
        $genderKey = strtolower($gender) === 'male' ? 'male' : 'female';
        $type = strtolower($type);

        $settingKey = "{$type}_{$genderKey}_creator_share_pct";
        $fallbackTypeKey = "{$type}_creator_share_pct";
        $globalSettingKey = 'creator_share_pct';

        $defaultPct = 60.0;

        if ($overridePct !== null && $overridePct > 0) {
            $creatorSharePct = (float) $overridePct;
        } else {
            $creatorSharePct = (float) PlatformSetting::get(
                $settingKey,
                PlatformSetting::get(
                    $fallbackTypeKey,
                    PlatformSetting::get($globalSettingKey, $defaultPct)
                )
            );
        }

        if ($creator) {
            // Unverified creators cannot earn creator credits
            if (! $creator->isCreatorVerified()) {
                return [
                    'gross_tokens' => (int) $tokenCost,
                    'creator_amount' => 0,
                    'platform_amount' => (int) $tokenCost,
                    'creator_share_pct' => 0.0,
                ];
            }

            $levelService = $this->levelService ?? app(LevelService::class);
            $levelData = $levelService->getUserLevelData($creator);

            if (! empty($levelData['creator_commission_pct'])) {
                $levelPct = (float) $levelData['creator_commission_pct'];
                $creatorSharePct = max($creatorSharePct, $levelPct);
            }
        }

        $creatorCredits = (int) round($tokenCost * ($creatorSharePct / 100.0));
        $platformTokens = (int) ($tokenCost - $creatorCredits);

        return [
            'gross_tokens' => (int) $tokenCost,
            'creator_amount' => $creatorCredits,
            'platform_amount' => $platformTokens,
            'creator_share_pct' => $creatorSharePct,
        ];
    }

    /**
     * Calculate monetization split for Paid Chat.
     */
    public function calculateChatSplit(User $creator, int $tokenCost): array
    {
        $gender = strtolower($creator->profile?->gender ?? 'female');

        return $this->calculateSplit($tokenCost, 'chat', $gender, $creator);
    }

    /**
     * Calculate monetization split for Call session.
     */
    public function calculateCallSplit(User $creator, int $minutes, ?int $pricePerMin = null): array
    {
        $rate = $pricePerMin ?? (int) PlatformSetting::get('video_call_rate_per_minute', 30);
        $grossTokens = $minutes * $rate;
        $gender = strtolower($creator->profile?->gender ?? 'female');

        return $this->calculateSplit($grossTokens, 'call', $gender, $creator);
    }

    /**
     * Calculate monetization split for Virtual Gift.
     */
    public function calculateGiftSplit(User $creator, Gift $gift): array
    {
        $gender = strtolower($creator->profile?->gender ?? 'female');
        $overridePct = $gift->recipient_share_percentage > 0 ? (float) $gift->recipient_share_percentage : null;

        return $this->calculateSplit($gift->coin_price, 'gift', $gender, $creator, $overridePct);
    }

    /**
     * Matching Token Cost — 100% platform revenue, 0 creator credits.
     */
    public function calculateMatchingSplit(): array
    {
        $tokenCost = (int) PlatformSetting::get('matching_token_cost', 50);

        return [
            'gross_tokens' => $tokenCost,
            'creator_credits' => 0.00,
            'platform_share_tokens' => $tokenCost,
        ];
    }
}
