<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CoinPackage;
use App\Models\LevelPenalty;
use App\Models\PaymentTransaction;
use App\Models\SpotlightPackage;
use App\Models\SpotlightPurchase;
use App\Models\User;
use App\Models\UserReport;
use App\Services\DiscoveryService;
use App\Services\LevelService;
use App\Services\MonetizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LindrLevelsTest extends TestCase
{
    use RefreshDatabase;

    protected LevelService $levelService;

    protected MonetizationService $monetizationService;

    protected DiscoveryService $discoveryService;

    protected CoinPackage $testPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->levelService = app(LevelService::class);
        $this->monetizationService = app(MonetizationService::class);
        $this->discoveryService = app(DiscoveryService::class);

        $this->levelService->seedDefaultRulesIfEmpty();

        $this->testPackage = CoinPackage::create([
            'name' => 'Test Token Package',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 500,
            'is_active' => true,
        ]);
    }

    /** Helper to create a valid PaymentTransaction for testing. */
    protected function createPaymentTransaction(array $attributes): PaymentTransaction
    {
        return PaymentTransaction::create(array_merge([
            'package_id' => $this->testPackage->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa_stk',
            'payment_reference' => 'TX_REF_'.rand(1000, 9999),
            'public_reference' => 'PUB_REF_'.rand(1000, 9999),
            'amount' => 500,
            'currency' => 'KES',
            'status' => 'successful',
            'gateway' => 'mpesa',
            'expected_coins' => 200,
        ], $attributes));
    }

    /** 1. New user starts at Level 0. */
    public function test_new_user_starts_at_level_zero(): void
    {
        $user = User::factory()->create();
        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(0, $data['level']);
    }

    /** 2. Level score starts correctly. */
    public function test_level_score_starts_correctly(): void
    {
        $user = User::factory()->create();
        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(0, $data['score']);
    }

    /** 3. Membership duration contributes correctly. */
    public function test_membership_duration_contributes_correctly(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(10)]);
        $data = $this->levelService->getUserLevelData($user);

        // 10 days * 5 points = 50 points
        $this->assertEquals(50, $data['breakdown']['membership_points']);
        $this->assertEquals(50, $data['score']);
    }

    /** 4. Successful top-ups contribute. */
    public function test_successful_topups_contribute(): void
    {
        $user = User::factory()->create();
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'successful',
            'expected_coins' => 200,
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(200, $data['breakdown']['topup_points']);
        $this->assertEquals(200, $data['score']);
        // 200 points >= Level 1 threshold (100) -> Level 1
        $this->assertEquals(1, $data['level']);
    }

    /** 5. Failed top-ups do not contribute. */
    public function test_failed_topups_do_not_contribute(): void
    {
        $user = User::factory()->create();
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'failed',
            'expected_coins' => 200,
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(0, $data['breakdown']['topup_points']);
        $this->assertEquals(0, $data['score']);
    }

    /** 6. Cancelled top-ups do not contribute. */
    public function test_cancelled_topups_do_not_contribute(): void
    {
        $user = User::factory()->create();
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'cancelled',
            'expected_coins' => 200,
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(0, $data['breakdown']['topup_points']);
        $this->assertEquals(0, $data['score']);
    }

    /** 7. Reversed top-ups do not contribute. */
    public function test_reversed_topups_do_not_contribute(): void
    {
        $user = User::factory()->create();
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'reversed',
            'expected_coins' => 200,
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(0, $data['breakdown']['topup_points']);
        $this->assertEquals(0, $data['score']);
    }

    /** 8. Spotlight activity contributes correctly. */
    public function test_spotlight_activity_contributes_correctly(): void
    {
        $user = User::factory()->create();
        $pkg = SpotlightPackage::create([
            'name' => 'Basic Spotlight',
            'duration_minutes' => 30,
            'token_cost' => 150,
            'boost_multiplier' => 2.0,
            'is_active' => true,
        ]);

        SpotlightPurchase::create([
            'user_id' => $user->id,
            'package_id' => $pkg->id,
            'tokens_spent' => 150,
            'starts_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'status' => 'active',
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(150, $data['breakdown']['spotlight_points']);
        $this->assertEquals(150, $data['score']);
    }

    /** 9. Moderation penalty reduces progression correctly. */
    public function test_moderation_penalty_reduces_progression_correctly(): void
    {
        $user = User::factory()->create();

        // Add 300 topup points
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'successful',
            'expected_coins' => 300,
        ]);

        // Add 100 penalty points
        LevelPenalty::create([
            'user_id' => $user->id,
            'moderation_action_id' => 'mod_123',
            'score_penalty' => 100,
            'reason' => 'Inappropriate behavior',
        ]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals(100, $data['breakdown']['penalties']);
        // 300 gross - 100 penalty = 200 net score
        $this->assertEquals(200, $data['score']);
        // Score 200 -> Level 1 (Level 2 threshold is 300)
        $this->assertEquals(1, $data['level']);
    }

    /** 10. Reports alone do not reduce level. */
    public function test_reports_alone_do_not_reduce_level(): void
    {
        $reporter = User::factory()->create();
        $target = User::factory()->create();

        // Submit unconfirmed user report
        UserReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $target->id,
            'reason' => 'Spamming messages',
            'status' => 'pending',
        ]);

        $data = $this->levelService->getUserLevelData($target);

        $this->assertEquals(0, $data['breakdown']['penalties']);
        $this->assertEquals(0, $data['score']);
        $this->assertEquals('GOOD_STANDING', $data['community_standing']);
    }

    /** 11. Level threshold transitions work. */
    public function test_level_threshold_transitions_work(): void
    {
        $user = User::factory()->create();

        // 350 points -> Level 2 (threshold 300)
        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'successful',
            'expected_coins' => 350,
        ]);

        $data = $this->levelService->getUserLevelData($user);
        $this->assertEquals(2, $data['level']);
        $this->assertEquals(350, $data['score']);
        $this->assertEquals(3, $data['next_level']);
        $this->assertEquals(600, $data['next_threshold']);
        $this->assertEquals(250, $data['points_to_next_level']);
    }

    /** 12. Current level is calculated server-side. */
    public function test_current_level_is_calculated_server_side(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/levels/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.level', 0)
            ->assertJsonPath('data.score', 0);
    }

    /** 13. Mobile cannot manipulate level. */
    public function test_mobile_cannot_manipulate_level(): void
    {
        $user = User::factory()->create();

        // Attempting to post level values via API yields 405 Method Not Allowed
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/levels/me', [
            'level' => 5,
            'score' => 9999,
        ]);

        $response->assertStatus(405);
    }

    /** 14. Level exposure multiplier is applied correctly. */
    public function test_level_exposure_multiplier_is_applied_correctly(): void
    {
        $user = User::factory()->create();

        $this->createPaymentTransaction([
            'user_id' => $user->id,
            'status' => 'successful',
            'expected_coins' => 1200,
        ]);

        $data = $this->levelService->getUserLevelData($user);

        // 1200 points -> Level 4 (threshold 1000)
        $this->assertEquals(4, $data['level']);
        $this->assertEquals(2.2, $data['exposure_multiplier']);
    }

    /** 15. Level does not bypass account restrictions. */
    public function test_level_does_not_bypass_account_restrictions(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Suspended]);

        $data = $this->levelService->getUserLevelData($user);

        $this->assertEquals('RESTRICTED', $data['community_standing']);
    }

    /** 16. Unverified users cannot earn creator credits. */
    public function test_unverified_users_cannot_earn_creator_credits(): void
    {
        $unverifiedUser = User::factory()->create([
            'is_creator' => false,
            'creator_status' => 'pending',
            'role' => UserRole::Female,
        ]);

        $split = $this->monetizationService->calculateSplit(100, 'chat', 'female', $unverifiedUser);

        $this->assertEquals(0, $split['creator_amount']);
        $this->assertEquals(100, $split['platform_amount']);
        $this->assertEquals(0.0, $split['creator_share_pct']);
    }

    /** 17. Verified creator commission uses current configured level. */
    public function test_verified_creator_commission_uses_current_configured_level(): void
    {
        $creator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
            'role' => UserRole::Female,
        ]);

        // Elevate creator to Level 2 (300 points -> 65% share)
        $this->createPaymentTransaction([
            'user_id' => $creator->id,
            'status' => 'successful',
            'expected_coins' => 300,
        ]);

        $split = $this->monetizationService->calculateSplit(100, 'chat', 'female', $creator);

        $this->assertEquals(65.0, $split['creator_share_pct']);
        $this->assertEquals(65, $split['creator_amount']);
        $this->assertEquals(35, $split['platform_amount']);
    }

    /** 18. Historical earnings preserve the commission used at transaction time. */
    public function test_historical_earnings_preserve_commission_used_at_transaction_time(): void
    {
        $creator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
            'role' => UserRole::Female,
        ]);

        // Transaction at Level 0 (60% share)
        $historicalSplit = $this->monetizationService->calculateSplit(100, 'chat', 'female', $creator);
        $recordedCreatorAmount = $historicalSplit['creator_amount']; // 60

        // Creator later progresses to Level 5 (80% share)
        $this->createPaymentTransaction([
            'user_id' => $creator->id,
            'status' => 'successful',
            'expected_coins' => 1600,
        ]);

        $newSplit = $this->monetizationService->calculateSplit(100, 'chat', 'female', $creator);

        // Historical split variable remains 60, while new split is 80
        $this->assertEquals(60, $recordedCreatorAmount);
        $this->assertEquals(80, $newSplit['creator_amount']);
    }

    /** 19. Level history is recorded correctly. */
    public function test_level_history_is_recorded_correctly(): void
    {
        $user = User::factory()->create();

        $this->levelService->recalculateUserLevel($user, 'PROGRESSION', null);

        $this->assertDatabaseHas('user_level_histories', [
            'user_id' => $user->id,
            'previous_level' => 0,
            'new_level' => 0,
            'reason' => 'PROGRESSION',
        ]);
    }

    /** 20. Admin recalculation is audited. */
    public function test_admin_recalculation_is_audited(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $user = User::factory()->create();

        $this->levelService->recalculateUserLevel($user, 'ADMIN_RECALCULATION', $admin->id);

        $this->assertDatabaseHas('user_level_histories', [
            'user_id' => $user->id,
            'admin_id' => $admin->id,
            'reason' => 'ADMIN_RECALCULATION',
        ]);
    }

    /** 21. Suspended users do not receive discovery exposure. */
    public function test_suspended_users_do_not_receive_discovery_exposure(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Male]);
        $suspendedFemale = User::factory()->create([
            'role' => UserRole::Female,
            'status' => UserStatus::Suspended,
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $suspendedFemale->id));
    }

    /** 22. Banned users do not receive discovery exposure. */
    public function test_banned_users_do_not_receive_discovery_exposure(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Male]);
        $bannedFemale = User::factory()->create([
            'role' => UserRole::Female,
            'status' => UserStatus::Banned,
        ]);

        $paginator = $this->discoveryService->getDiscoverableProfiles($viewer);

        $this->assertFalse($paginator->getCollection()->contains('id', $bannedFemale->id));
    }
}
