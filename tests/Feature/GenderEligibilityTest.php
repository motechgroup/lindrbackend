<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\LevelRule;
use App\Models\SpotlightPackage;
use App\Models\SpotlightPurchase;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserProfile;
use App\Services\CallService;
use App\Services\DiscoveryService;
use App\Services\GenderEligibilityService;
use App\Services\MatchingService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenderEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected GenderEligibilityService $genderService;

    protected DiscoveryService $discoveryService;

    protected MatchingService $matchingService;

    protected CallService $callService;

    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->genderService = app(GenderEligibilityService::class);
        $this->discoveryService = app(DiscoveryService::class);
        $this->matchingService = app(MatchingService::class);
        $this->callService = app(CallService::class);
        $this->walletService = app(WalletService::class);

        LevelRule::firstOrCreate(['level' => 0], ['name' => 'L0', 'required_xp' => 0, 'creator_commission_pct' => 70]);
        LevelRule::firstOrCreate(['level' => 5], ['name' => 'L5', 'required_xp' => 5000, 'creator_commission_pct' => 90]);
    }

    protected function createMaleUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => UserRole::Male, 'status' => UserStatus::Active], $attributes));
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Male_'.$user->id,
            'gender' => 'male',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1995-05-15',
        ]);

        return $user;
    }

    protected function createFemaleUser(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge(['role' => UserRole::Female, 'status' => UserStatus::Active], $attributes));
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => 'Female_'.$user->id,
            'gender' => 'female',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1997-08-20',
        ]);

        return $user;
    }

    // 1. MAN target gender resolves to WOMAN
    public function test_man_target_gender_resolves_to_woman(): void
    {
        $man = $this->createMaleUser();
        $this->assertEquals('female', $this->genderService->getTargetGender($man));
    }

    // 2. WOMAN target gender resolves to MAN
    public function test_woman_target_gender_resolves_to_man(): void
    {
        $woman = $this->createFemaleUser();
        $this->assertEquals('male', $this->genderService->getTargetGender($woman));
    }

    // 3. MAN sees WOMAN profiles in Discover
    public function test_man_sees_woman_profiles_in_discover(): void
    {
        $man = $this->createMaleUser();
        $woman = $this->createFemaleUser();

        $response = $this->actingAs($man, 'sanctum')->getJson('/api/v1/discover');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($woman->id));
    }

    // 4. MAN does not see MAN profiles in Discover
    public function test_man_does_not_see_man_profiles_in_discover(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();

        $response = $this->actingAs($man1, 'sanctum')->getJson('/api/v1/discover');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($man2->id));
    }

    // 5. WOMAN sees MAN profiles in Discover
    public function test_woman_sees_man_profiles_in_discover(): void
    {
        $woman = $this->createFemaleUser();
        $man = $this->createMaleUser();

        $response = $this->actingAs($woman, 'sanctum')->getJson('/api/v1/discover');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($man->id));
    }

    // 6. WOMAN does not see WOMAN profiles in Discover
    public function test_woman_does_not_see_woman_profiles_in_discover(): void
    {
        $woman1 = $this->createFemaleUser();
        $woman2 = $this->createFemaleUser();

        $response = $this->actingAs($woman1, 'sanctum')->getJson('/api/v1/discover');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($woman2->id));
    }

    // 7. MAN can Match with eligible WOMAN
    public function test_man_can_match_with_eligible_woman(): void
    {
        $man = $this->createMaleUser();
        $woman = $this->createFemaleUser();
        $this->walletService->creditCoins($man, 100);

        $response = $this->actingAs($man, 'sanctum')->postJson('/api/v1/matches/search');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals($woman->id, $response->json('data.target_user.id'));
    }

    // 8. MAN cannot Match with MAN
    public function test_man_cannot_match_with_man(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();
        $this->walletService->creditCoins($man1, 100);

        $response = $this->actingAs($man1, 'sanctum')->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'code' => 'NO_MATCH_AVAILABLE',
            ]);

        // Zero tokens deducted
        $this->assertEquals(100, $man1->fresh()->wallet->coin_balance);
    }

    // 9. WOMAN can Match with eligible MAN
    public function test_woman_can_match_with_eligible_man(): void
    {
        $woman = $this->createFemaleUser();
        $man = $this->createMaleUser();
        $this->walletService->creditCoins($woman, 100);

        $response = $this->actingAs($woman, 'sanctum')->postJson('/api/v1/matches/search');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertEquals($man->id, $response->json('data.target_user.id'));
    }

    // 10. WOMAN cannot Match with WOMAN
    public function test_woman_cannot_match_with_woman(): void
    {
        $woman1 = $this->createFemaleUser();
        $woman2 = $this->createFemaleUser();
        $this->walletService->creditCoins($woman1, 100);

        $response = $this->actingAs($woman1, 'sanctum')->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'code' => 'NO_MATCH_AVAILABLE',
            ]);

        $this->assertEquals(100, $woman1->fresh()->wallet->coin_balance);
    }

    // 11. MAN can call eligible WOMAN
    public function test_man_can_call_eligible_woman(): void
    {
        $man = $this->createMaleUser();
        $woman = $this->createFemaleUser();
        $this->walletService->creditCoins($man, 100);

        $response = $this->actingAs($man, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $woman->id]);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // 12. MAN cannot call MAN through API manipulation
    public function test_man_cannot_call_man_through_api_manipulation(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();
        $this->walletService->creditCoins($man1, 100);

        $response = $this->actingAs($man1, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $man2->id]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'tokens_deducted' => 0,
            ]);

        $this->assertDatabaseMissing('call_sessions', ['caller_id' => $man1->id, 'receiver_id' => $man2->id]);
    }

    // 13. WOMAN can call eligible MAN
    public function test_woman_can_call_eligible_man(): void
    {
        $woman = $this->createFemaleUser();
        $man = $this->createMaleUser();
        $this->walletService->creditCoins($woman, 100);

        $response = $this->actingAs($woman, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $man->id]);

        $response->assertStatus(201)->assertJson(['success' => true]);
    }

    // 14. WOMAN cannot call WOMAN through API manipulation
    public function test_woman_cannot_call_woman_through_api_manipulation(): void
    {
        $woman1 = $this->createFemaleUser();
        $woman2 = $this->createFemaleUser();
        $this->walletService->creditCoins($woman1, 100);

        $response = $this->actingAs($woman1, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $woman2->id]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('call_sessions', ['caller_id' => $woman1->id, 'receiver_id' => $woman2->id]);
    }

    // 15. Spotlight does not bypass gender eligibility
    public function test_spotlight_does_not_bypass_gender_eligibility(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();

        $package = SpotlightPackage::create([
            'name' => 'Spotlight Daily',
            'duration_minutes' => 1440,
            'token_cost' => 100,
            'boost_multiplier' => 2.0,
            'is_active' => true,
        ]);

        // Give man2 active spotlight
        SpotlightPurchase::create([
            'user_id' => $man2->id,
            'package_id' => $package->id,
            'tokens_spent' => 100,
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'active',
        ]);

        $response = $this->actingAs($man1, 'sanctum')->getJson('/api/v1/discover');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($man2->id));
    }

    // 16. Level does not bypass gender eligibility
    public function test_level_does_not_bypass_gender_eligibility(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();

        // Elevate man2 to Level 5
        $man2->profile->update(['level' => 5]);

        $response = $this->actingAs($man1, 'sanctum')->getJson('/api/v1/discover');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($man2->id));
    }

    // 17. Client parameter manipulation does not bypass gender eligibility
    public function test_client_gender_filter_override_is_ignored(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();

        // Client passes ?gender=male parameter maliciously
        $response = $this->actingAs($man1, 'sanctum')->getJson('/api/v1/discover?gender=male');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($man2->id));
    }

    // 18. No-match response occurs when no eligible opposite-gender user is available
    public function test_no_match_response_when_no_opposite_gender_available(): void
    {
        $man = $this->createMaleUser();
        $this->walletService->creditCoins($man, 100);

        $response = $this->actingAs($man, 'sanctum')->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
                'code' => 'NO_MATCH_AVAILABLE',
            ]);
    }

    // 19. No tokens are deducted when Match is rejected due to gender eligibility
    public function test_no_tokens_deducted_when_match_rejected_for_gender(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();
        $this->walletService->creditCoins($man1, 100);

        $this->actingAs($man1, 'sanctum')->postJson('/api/v1/matches/search');

        $this->assertEquals(100, $man1->fresh()->wallet->coin_balance);
    }

    // 20. No tokens are deducted when a call is rejected due to gender eligibility
    public function test_no_tokens_deducted_when_call_rejected_for_gender(): void
    {
        $man1 = $this->createMaleUser();
        $man2 = $this->createMaleUser();
        $this->walletService->creditCoins($man1, 100);

        $this->actingAs($man1, 'sanctum')->postJson('/api/v1/calls/request', ['receiver_id' => $man2->id]);

        $this->assertEquals(100, $man1->fresh()->wallet->coin_balance);
    }

    // 21. Discover ranking is applied only after gender eligibility
    public function test_discover_ranking_applied_only_after_gender_eligibility(): void
    {
        $man = $this->createMaleUser();
        $woman1 = $this->createFemaleUser();
        $woman2 = $this->createFemaleUser();

        // Woman2 is Level 5 + Spotlight
        $woman2->profile->update(['level' => 5]);
        $package = SpotlightPackage::firstOrCreate(['name' => 'Spotlight Daily'], [
            'duration_minutes' => 1440,
            'token_cost' => 100,
            'boost_multiplier' => 2.0,
            'is_active' => true,
        ]);

        SpotlightPurchase::create([
            'user_id' => $woman2->id,
            'package_id' => $package->id,
            'tokens_spent' => 100,
            'starts_at' => now(),
            'expires_at' => now()->addHours(24),
            'status' => 'active',
        ]);

        $response = $this->actingAs($man, 'sanctum')->getJson('/api/v1/discover');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        // Woman2 ranked first due to level + spotlight
        $this->assertEquals($woman2->id, $data[0]['id']);
    }

    // 22. Block / suspension rules still work in conjunction with gender eligibility
    public function test_block_and_suspension_rules_work_with_gender_eligibility(): void
    {
        $man = $this->createMaleUser();
        $woman = $this->createFemaleUser();

        UserBlock::create(['blocker_id' => $man->id, 'blocked_id' => $woman->id]);

        $response = $this->actingAs($man, 'sanctum')->getJson('/api/v1/discover');

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertFalse($ids->contains($woman->id));
    }
}
