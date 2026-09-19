<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Like;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DiscoveryService
{
    public function __construct(
        public BlockService $blockService,
        public LevelService $levelService,
        public SpotlightService $spotlightService,
        public PresenceService $presenceService,
        public DiscoverRankingService $discoverRankingService,
        public GenderEligibilityService $genderEligibilityService,
        public SafetyService $safetyService
    ) {}

    /**
     * Get discoverable users for a given user with filters and Discover ranking engine.
     *
     * @param  array<string, mixed>  $filters
     */
    public function getDiscoverableProfiles(User $user, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        // 1. Exclude swiped and blocked relationships (Safety & Privacy)
        $swipedUserIds = Like::where('user_id', $user->id)->pluck('target_user_id');
        $blockedUserIds = $this->blockService->getBlockedUserIds($user);
        $excludeIds = $swipedUserIds->merge($blockedUserIds)->unique();

        // 2. Base query: STRICTLY active users only (Banned & Suspended accounts are excluded here)
        $query = User::query()
            ->where('id', '!=', $user->id)
            ->where('status', UserStatus::Active)
            ->whereNotIn('id', $excludeIds);

        // 3. HARD RULE: Enforce backend opposite-gender eligibility before ranking
        $query = $this->genderEligibilityService->applyGenderFilter($query, $user);

        // Apply additional profile filters (ignoring client-supplied gender override for security)
        $query->whereHas('profile', function ($q) use ($filters) {
            if (! empty($filters['city'])) {
                $q->where('city', 'ILIKE', '%'.$filters['city'].'%')
                    ->orWhere('city', 'LIKE', '%'.$filters['city'].'%');
            }

            if (! empty($filters['min_age'])) {
                $maxDob = now()->subYears((int) $filters['min_age'])->format('Y-m-d');
                $q->where('date_of_birth', '<=', $maxDob);
            }

            if (! empty($filters['max_age'])) {
                $minDob = now()->subYears((int) $filters['max_age'] + 1)->format('Y-m-d');
                $q->where('date_of_birth', '>=', $minDob);
            }
        });

        $paginator = $query->with(['profile', 'photos', 'interests'])
            ->latest()
            ->paginate($perPage);

        // Filter out offline profiles & calculate ranking scores
        $rankedCollection = $paginator->getCollection()
            ->filter(function (User $profileUser) use ($user) {
                if (! $this->safetyService->canDiscover($user, $profileUser)) {
                    return false;
                }

                $presence = $this->presenceService->getUserPresence($profileUser);

                // Exclude offline users from active Discover grid
                return ($presence['status'] ?? 'offline') !== 'offline';
            })
            ->map(function (User $profileUser) use ($user) {
                $score = $this->discoverRankingService->calculateRankingScore($profileUser, $user);
                $levelData = $this->levelService->getUserLevelData($profileUser);
                $spotlight = $this->spotlightService->getActiveSpotlight($profileUser);

                $profileUser->level_data = $levelData;
                $profileUser->is_spotlight_active = ! is_null($spotlight);
                $profileUser->internal_ranking_score = $score;

                return $profileUser;
            })
            ->sortByDesc('internal_ranking_score')
            ->values();

        // Strip internal_ranking_score before returning to API
        $rankedCollection->transform(function (User $profileUser) {
            unset($profileUser->internal_ranking_score);

            return $profileUser;
        });

        $paginator->setCollection($rankedCollection);

        return $paginator;
    }
}
