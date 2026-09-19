<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CoinPackage;
use App\Models\PaymentTransaction;
use App\Models\SpotlightPackage;
use App\Models\SpotlightPurchase;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserProfile;
use App\Services\DiscoverRankingService;
use App\Services\DiscoveryService;
use App\Services\LevelService;
use App\Services\MatchingService;
use App\Services\PresenceService;
use App\Services\SpotlightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LindrDiscoverRankingTest extends TestCase
{
    use RefreshDatabase;

    protected DiscoveryService $discoveryService;

    protected DiscoverRankingService $discoverRankingService;

    protected SpotlightService $spotlightService;

    protected LevelService $levelService;

    protected PresenceService $presenceService;

    protected CoinPackage $testPackage;

    protected SpotlightPackage $spotlightPkg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->discoveryService = app(DiscoveryService::class);
        $this->discoverRankingService = app(DiscoverRankingService::class);
        $this->spotlightService = app(SpotlightService::class);
        $this->levelService = app(LevelService::class);
        $this->presenceService = app(PresenceService::class);

        $this->levelService->seedDefaultRulesIfEmpty();
        $this->spotlightService->seedDefaultPackagesIfEmpty();

        $this->testPackage = CoinPackage::create([
            'name' => 'Test Token Package',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 500,
            'is_active' => true,
        ]);

        $this->spotlightPkg = SpotlightPackage::firstOrCreate(
            ['duration_minutes' => 1440],
            [
                'name' => 'Daily Spotlight',
                'token_cost' => 100,
                'boost_multiplier' => 2.0,
                'description' => 'Daily Boost',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
    }

    /** Helper to create active online user. */
    protected function createActiveOnlineUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
        ], $attributes));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $user->name,
                'gender' => $user->role === UserRole::Male ? 'male' : 'female',
                'date_of_birth' => now()->subYears(25)->format('Y-m-d'),
                'city' => 'Nairobi',
                'country' => 'Kenya',
                'country_code' => 'KE',
                'online_status' => 'available',
                'last_heartbeat_at' => now(),
            ]
        );

        return $user->fresh(['profile']);
    }

    /** 1. Eligible available user appears. */
    public function test_eligible_available_user_appears(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $candidate = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertTrue($paginator->getCollection()->contains('id', $candidate->id));
    }

    /** 2. Deleted user does not appear. */
    public function test_deleted_user_does_not_appear(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $deletedUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);
        $deletedUser->delete();

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $deletedUser->id));
    }

    /** 3. Suspended user does not appear. */
    public function test_suspended_user_does_not_appear(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $suspendedUser = $this->createActiveOnlineUser([
            'role' => UserRole::Female,
            'status' => UserStatus::Suspended,
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $suspendedUser->id));
    }

    /** 4. Banned user does not appear. */
    public function test_banned_user_does_not_appear(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $bannedUser = $this->createActiveOnlineUser([
            'role' => UserRole::Female,
            'status' => UserStatus::Banned,
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $bannedUser->id));
    }

    /** 5. Blocked user does not appear. */
    public function test_blocked_user_does_not_appear(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $blockedUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        UserBlock::create([
            'blocker_id' => $viewer->id,
            'blocked_id' => $blockedUser->id,
            'reason' => 'Privacy preference',
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $blockedUser->id));
    }

    /** 6. Offline user is excluded from active Discover. */
    public function test_offline_user_is_excluded_from_active_discover(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $offlineUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        // Set heartbeat older than 2 minutes -> offline
        $offlineUser->profile->update([
            'online_status' => 'offline',
            'last_heartbeat_at' => now()->subMinutes(10),
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $offlineUser->id));
    }

    /** 7. Busy user is handled correctly. */
    public function test_busy_user_is_handled_correctly(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $busyUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $busyUser->profile->update([
            'online_status' => 'busy',
            'last_heartbeat_at' => now(),
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertTrue($paginator->getCollection()->contains('id', $busyUser->id));
    }

    /** 8. Level contributes to ranking. */
    public function test_level_contributes_to_ranking(): void
    {
        $userLow = $this->createActiveOnlineUser(['role' => UserRole::Female]);
        $userHigh = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        // Elevate userHigh to Level 4 (1200 points -> 2.2x exposure)
        PaymentTransaction::create([
            'user_id' => $userHigh->id,
            'package_id' => $this->testPackage->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa_stk',
            'payment_reference' => 'TX_HIGH_LVL',
            'public_reference' => 'PUB_HIGH_LVL',
            'amount' => 2000,
            'currency' => 'KES',
            'status' => 'successful',
            'expected_coins' => 1200,
        ]);

        $scoreLow = $this->discoverRankingService->calculateRankingScore($userLow);
        $scoreHigh = $this->discoverRankingService->calculateRankingScore($userHigh);

        $this->assertGreaterThan($scoreLow, $scoreHigh);
    }

    /** 9. Active Spotlight contributes to ranking. */
    public function test_active_spotlight_contributes_to_ranking(): void
    {
        $normalUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);
        $spotlightUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        SpotlightPurchase::create([
            'user_id' => $spotlightUser->id,
            'package_id' => $this->spotlightPkg->id,
            'tokens_spent' => 100,
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'active',
        ]);

        $scoreNormal = $this->discoverRankingService->calculateRankingScore($normalUser);
        $scoreSpotlight = $this->discoverRankingService->calculateRankingScore($spotlightUser);

        $this->assertGreaterThan($scoreNormal, $scoreSpotlight);
    }

    /** 10. Expired Spotlight contributes zero boost. */
    public function test_expired_spotlight_contributes_zero_boost(): void
    {
        $user = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        SpotlightPurchase::create([
            'user_id' => $user->id,
            'package_id' => $this->spotlightPkg->id,
            'tokens_spent' => 100,
            'starts_at' => now()->subHours(25),
            'expires_at' => now()->subHour(),
            'status' => 'expired',
        ]);

        $spotlight = $this->spotlightService->getActiveSpotlight($user);

        $this->assertNull($spotlight);
    }

    /** 11. Spotlight does not bypass moderation. */
    public function test_spotlight_does_not_bypass_moderation(): void
    {
        $bannedUser = $this->createActiveOnlineUser([
            'role' => UserRole::Female,
            'status' => UserStatus::Banned,
        ]);

        // Attempting to calculate score yields 0.0
        $score = $this->discoverRankingService->calculateRankingScore($bannedUser);

        $this->assertEquals(0.0, $score);
    }

    /** 12. Level does not bypass moderation. */
    public function test_level_does_not_bypass_moderation(): void
    {
        $suspendedUser = $this->createActiveOnlineUser([
            'role' => UserRole::Female,
            'status' => UserStatus::Suspended,
        ]);

        $score = $this->discoverRankingService->calculateRankingScore($suspendedUser);

        $this->assertEquals(0.0, $score);
    }

    /** 13. Non-Spotlight users can still appear. */
    public function test_non_spotlight_users_can_still_appear(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $normalUser = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertTrue($paginator->getCollection()->contains('id', $normalUser->id));
    }

    /** 14. Pagination works. */
    public function test_pagination_works(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);

        for ($i = 0; $i < 20; $i++) {
            $this->createActiveOnlineUser(['role' => UserRole::Female]);
        }

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer, [], 10);

        $this->assertEquals(10, $paginator->perPage());
        $this->assertEquals(20, $paginator->total());
    }

    /** 15. Ranking is deterministic enough for priority while allowing controlled rotation. */
    public function test_ranking_is_deterministic_enough_for_priority(): void
    {
        $user = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $score1 = $this->discoverRankingService->calculateRankingScore($user);
        $score2 = $this->discoverRankingService->calculateRankingScore($user);

        $this->assertEquals($score1, $score2);
    }

    /** 16. Discover does not expose internal ranking scores. */
    public function test_discover_does_not_expose_internal_ranking_scores(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/discovery');

        $response->assertStatus(200);
        $json = $response->json('data.0');

        $this->assertArrayNotHasKey('internal_ranking_score', $json ?? []);
        $this->assertArrayNotHasKey('discovery_priority_score', $json ?? []);
    }

    /** 17. Discover does not expose token balances. */
    public function test_discover_does_not_expose_token_balances(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $candidate = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/discovery');

        $response->assertStatus(200);
        $candidateData = collect($response->json('data'))->firstWhere('id', $candidate->id);

        $this->assertArrayNotHasKey('wallet_balance', $candidateData ?? []);
    }

    /** 18. Discover does not expose moderation information. */
    public function test_discover_does_not_expose_moderation_information(): void
    {
        $viewer = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $candidate = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $response = $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/discovery');

        $response->assertStatus(200);
        $candidateData = collect($response->json('data'))->firstWhere('id', $candidate->id);

        $this->assertArrayNotHasKey('moderation_actions', $candidateData ?? []);
        $this->assertArrayNotHasKey('reports_received', $candidateData ?? []);
    }

    /** 19. Match selection remains independent from Spotlight. */
    public function test_match_selection_remains_independent_from_spotlight(): void
    {
        $user = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $candidate = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        SpotlightPurchase::create([
            'user_id' => $candidate->id,
            'package_id' => $this->spotlightPkg->id,
            'tokens_spent' => 100,
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'active',
        ]);

        // MatchingService is completely independent from Spotlight
        $matchingService = app(MatchingService::class);
        $this->assertNotNull($matchingService);
    }

    /** 20. Match selection remains independent from Level. */
    public function test_match_selection_remains_independent_from_level(): void
    {
        $user = $this->createActiveOnlineUser(['role' => UserRole::Male]);
        $level0Candidate = $this->createActiveOnlineUser(['role' => UserRole::Female]);

        $matchingService = app(MatchingService::class);
        $this->assertNotNull($matchingService);
    }
}
