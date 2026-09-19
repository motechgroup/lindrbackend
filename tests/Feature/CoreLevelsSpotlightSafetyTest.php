<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Exceptions\CallUnavailableException;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\SpotlightPackage;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CallService;
use App\Services\LevelService;
use App\Services\ModerationService;
use App\Services\MonetizationService;
use App\Services\SpotlightService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoreLevelsSpotlightSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_user_starts_at_level_zero(): void
    {
        $user = User::factory()->female()->create();
        $levelService = app(LevelService::class);
        $levelData = $levelService->getUserLevelData($user);

        $this->assertEquals(0, $levelData['level']);
        $this->assertEquals('Level 0', $levelData['level_name']);
        $this->assertEquals('GOOD_STANDING', $levelData['community_standing']);
    }

    public function test_level_calculation_includes_spotlight_topups_membership_minus_penalties(): void
    {
        $user = User::factory()->female()->create(['created_at' => now()->subDays(20)]);
        $walletService = app(WalletService::class);
        $spotlightService = app(SpotlightService::class);
        $levelService = app(LevelService::class);

        // Add tokens and purchase spotlight
        $walletService->creditCoins($user, 500);
        $package = SpotlightPackage::create(['name' => 'Test Boost', 'duration_minutes' => 60, 'token_cost' => 100, 'boost_multiplier' => 2.0]);
        $spotlightService->purchaseSpotlight($user, $package->id);

        // Add coin purchase topup record
        $coinPkg = CoinPackage::create(['name' => 'Test Pack', 'coin_amount' => 250, 'price_kes' => 100.00, 'display_order' => 1]);
        CoinPurchase::create([
            'user_id' => $user->id,
            'package_id' => $coinPkg->id,
            'phone_number' => '254711111111',
            'payment_reference' => 'REF-1234',
            'amount_kes' => 100.00,
            'currency' => 'KES',
            'coin_amount' => 250,
            'coins_credited' => 250,
            'status' => 'completed',
        ]);

        $levelData = $levelService->getUserLevelData($user);

        // 100 (spotlight) + 250 (topup) + 100 (20 days * 5) = 450 points => Level 2 (min 300)
        $this->assertEquals(450, $levelData['score']);
        $this->assertEquals(2, $levelData['level']);
    }

    public function test_moderation_penalty_reduces_level_score(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->female()->create(['created_at' => now()->subDays(20)]);

        $levelService = app(LevelService::class);
        $moderationService = app(ModerationService::class);

        // Apply 200 penalty points
        $moderationService->recordModerationAction(
            admin: $admin,
            targetUserId: $targetUser->id,
            action: 'level_penalty',
            reason: 'Misconduct penalty',
            scorePenalty: 200
        );

        $levelData = $levelService->getUserLevelData($targetUser);
        $this->assertEquals('WARNING', $levelData['community_standing']);
        $this->assertEquals(200, $levelData['breakdown']['penalties']);
    }

    public function test_creator_commission_uses_dynamic_level_rate(): void
    {
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $monetizationService = app(MonetizationService::class);

        // Base Level 0 commission is 60%
        $split = $monetizationService->calculateSplit(100, 'call', 'female', $creator);
        $this->assertEquals(60.0, $split['creator_share_pct']);
        $this->assertEquals(60, $split['creator_amount']);
        $this->assertEquals(40, $split['platform_amount']);
    }

    public function test_unverified_creator_cannot_earn(): void
    {
        $unverifiedCreator = User::factory()->female()->create(['is_creator' => false, 'creator_status' => 'none']);
        $this->assertFalse($unverifiedCreator->isCreatorVerified());
    }

    public function test_spotlight_purchase_uses_tokens_and_gives_zero_creator_credits(): void
    {
        $user = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $spotlightService = app(SpotlightService::class);

        $walletService->creditCoins($user, 200);
        $package = SpotlightPackage::create(['name' => '1 Hr', 'duration_minutes' => 60, 'token_cost' => 50, 'boost_multiplier' => 2.0]);

        $purchase = $spotlightService->purchaseSpotlight($user, $package->id);

        $this->assertEquals('active', $purchase->status);
        $this->assertEquals(150, $walletService->getWallet($user)->coin_balance);
        $this->assertDatabaseCount('creator_credit_ledgers', 0);
    }

    public function test_suspended_user_cannot_purchase_or_receive_spotlight(): void
    {
        $user = User::factory()->male()->create(['status' => UserStatus::Suspended]);
        $spotlightService = app(SpotlightService::class);

        $this->expectException(\DomainException::class);
        $spotlightService->purchaseSpotlight($user, 1);
    }

    public function test_user_can_submit_report(): void
    {
        $reporter = User::factory()->male()->create();
        $reported = User::factory()->female()->create();

        $response = $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_id' => $reported->id,
                'category' => 'harassment',
                'description' => 'Inappropriate text messages',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'category' => 'harassment',
            'status' => 'pending',
        ]);
    }

    public function test_user_cannot_report_themselves(): void
    {
        $user = User::factory()->male()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_id' => $user->id,
                'category' => 'spam',
            ]);

        $response->assertStatus(422);
    }

    public function test_duplicate_report_protection_prevents_duplicate_entries(): void
    {
        $reporter = User::factory()->male()->create();
        $reported = User::factory()->female()->create();

        $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', ['reported_id' => $reported->id, 'category' => 'spam']);

        $response = $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', ['reported_id' => $reported->id, 'category' => 'spam']);

        $response->assertStatus(201);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_admin_can_suspend_or_ban_user_and_audit_action(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->female()->create();
        $moderationService = app(ModerationService::class);

        $action = $moderationService->recordModerationAction(
            admin: $admin,
            targetUserId: $target->id,
            action: 'ban',
            reason: 'Confirmed severe violations'
        );

        $this->assertEquals(UserStatus::Banned, $target->fresh()->status);
        $this->assertDatabaseHas('moderation_actions', [
            'id' => $action->id,
            'action' => 'ban',
            'target_user_id' => $target->id,
        ]);
    }

    public function test_banned_users_disappear_from_discovery(): void
    {
        $viewer = User::factory()->male()->create();
        $bannedFemale = User::factory()->female()->create(['status' => UserStatus::Banned]);
        $activeFemale = User::factory()->female()->create(['status' => UserStatus::Active]);

        UserProfile::factory()->create(['user_id' => $bannedFemale->id]);
        UserProfile::factory()->create(['user_id' => $activeFemale->id]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/discovery');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertNotContains($bannedFemale->id, $ids);
        $this->assertContains($activeFemale->id, $ids);
    }

    public function test_banned_user_cannot_initiate_call(): void
    {
        $bannedMale = User::factory()->male()->create(['status' => UserStatus::Banned]);
        $female = User::factory()->female()->create();

        $callService = app(CallService::class);
        $walletService = app(WalletService::class);
        $walletService->creditCoins($bannedMale, 100);

        $this->expectException(CallUnavailableException::class);
        $callService->requestCall($bannedMale, $female);
    }

    public function test_guidelines_latest_endpoint_returns_published_policy(): void
    {
        $user = User::factory()->male()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/guidelines/latest');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version', 'v1.0');
    }
}
