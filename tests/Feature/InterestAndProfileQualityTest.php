<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Interest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterestAndProfileQualityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::set('max_user_interests', 10);
    }

    protected function createActiveUser(array $attributes = [], string $gender = 'female'): User
    {
        $role = $gender === 'female' ? UserRole::Female : UserRole::Male;

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'role' => $role,
        ], $attributes));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => explode(' ', $user->name)[0],
                'gender' => $gender,
                'country' => 'KE',
                'country_code' => 'KE',
                'date_of_birth' => '1995-05-15',
                'online_status' => 'available',
                'last_heartbeat_at' => now(),
            ]
        );

        Wallet::firstOrCreate(['user_id' => $user->id], ['balance' => 100, 'credits' => 500]);

        return $user->fresh(['profile']);
    }

    public function test_1_admin_can_create_interest(): void
    {
        $interest = Interest::create([
            'name' => 'Photography',
            'slug' => 'photography',
            'category' => 'Arts',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('interests', [
            'name' => 'Photography',
            'category' => 'Arts',
        ]);
    }

    public function test_2_admin_can_activate_and_deactivate_interest(): void
    {
        $interest = Interest::create(['name' => 'Surfing', 'slug' => 'surfing', 'is_active' => true]);

        $interest->update(['is_active' => false]);
        $this->assertFalse((bool) $interest->fresh()->is_active);

        $interest->update(['is_active' => true]);
        $this->assertTrue((bool) $interest->fresh()->is_active);
    }

    public function test_3_user_can_select_interests(): void
    {
        $user = $this->createActiveUser();
        $i1 = Interest::create(['name' => 'Music', 'slug' => 'music']);
        $i2 = Interest::create(['name' => 'Travel', 'slug' => 'travel']);

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'interest_ids' => [$i1->id, $i2->id],
            ]);

        $response->assertStatus(200);
        $this->assertCount(2, $user->fresh()->interests);
        $this->assertContains('Music', $user->fresh()->profile->interests);
    }

    public function test_4_duplicate_interest_relationship_prevented(): void
    {
        $user = $this->createActiveUser();
        $i1 = Interest::create(['name' => 'Fitness', 'slug' => 'fitness']);

        $user->interests()->attach($i1->id);

        // Attempt duplicate attach
        try {
            $user->interests()->attach($i1->id);
        } catch (\Exception $e) {
            // Unique constraint caught cleanly
        }

        $this->assertEquals(1, $user->interests()->count());
    }

    public function test_5_user_can_remove_interest(): void
    {
        $user = $this->createActiveUser();
        $i1 = Interest::create(['name' => 'Gaming', 'slug' => 'gaming']);
        $i2 = Interest::create(['name' => 'Cooking', 'slug' => 'cooking']);

        $user->interests()->sync([$i1->id, $i2->id]);
        $this->assertEquals(2, $user->fresh()->interests()->count());

        // Remove Gaming
        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'interest_ids' => [$i2->id],
            ]);

        $this->assertEquals(1, $user->fresh()->interests()->count());
        $this->assertEquals('Cooking', $user->fresh()->interests()->first()->name);
    }

    public function test_6_interest_limit_enforced(): void
    {
        $user = $this->createActiveUser();
        $ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $item = Interest::create(['name' => "Interest_{$i}", 'slug' => "interest-{$i}"]);
            $ids[] = $item->id;
        }

        $response = $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'interest_ids' => $ids,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interest_ids']);
    }

    public function test_7_profile_returns_interests(): void
    {
        $user = $this->createActiveUser();
        $i1 = Interest::create(['name' => 'Reading', 'slug' => 'reading']);
        $user->interests()->attach($i1->id);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/profile');

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.interests.0', 'Reading');
    }

    public function test_8_shared_interests_calculated_correctly(): void
    {
        $viewer = $this->createActiveUser([], 'male');
        $target = $this->createActiveUser([], 'female');

        $i1 = Interest::create(['name' => 'Music', 'slug' => 'music']);
        $i2 = Interest::create(['name' => 'Travel', 'slug' => 'travel']);
        $i3 = Interest::create(['name' => 'Coding', 'slug' => 'coding']);

        $viewer->interests()->sync([$i1->id, $i2->id]);
        $target->interests()->sync([$i2->id, $i3->id]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/profile/{$target->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.shared_interests.0', 'Travel');
    }

    public function test_9_same_gender_user_remains_excluded_from_discover(): void
    {
        $manA = $this->createActiveUser([], 'male');
        $manB = $this->createActiveUser([], 'male');

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($manA);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertNotContains($manB->id, $ids);
    }

    public function test_10_blocked_user_remains_excluded(): void
    {
        $man = $this->createActiveUser([], 'male');
        $woman = $this->createActiveUser([], 'female');

        UserBlock::create(['blocker_id' => $man->id, 'blocked_id' => $woman->id]);

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($man);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertNotContains($woman->id, $ids);
    }

    public function test_11_suspended_user_remains_excluded(): void
    {
        $man = $this->createActiveUser([], 'male');
        $suspendedWoman = $this->createActiveUser(['status' => UserStatus::Suspended], 'female');

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($man);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertNotContains($suspendedWoman->id, $ids);
    }

    public function test_12_banned_user_remains_excluded(): void
    {
        $man = $this->createActiveUser([], 'male');
        $bannedWoman = $this->createActiveUser(['status' => UserStatus::Banned], 'female');

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($man);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertNotContains($bannedWoman->id, $ids);
    }

    public function test_13_offline_user_excluded_from_active_grid(): void
    {
        $man = $this->createActiveUser([], 'male');
        $offlineWoman = $this->createActiveUser([], 'female');
        $offlineWoman->profile->update(['online_status' => 'offline']);

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($man);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertNotContains($offlineWoman->id, $ids);
    }

    public function test_15_interests_do_not_become_hard_eligibility(): void
    {
        $man = $this->createActiveUser([], 'male');
        $womanNoShared = $this->createActiveUser([], 'female');

        $i1 = Interest::create(['name' => 'Cars', 'slug' => 'cars']);
        $i2 = Interest::create(['name' => 'Dancing', 'slug' => 'dancing']);

        $man->interests()->sync([$i1->id]);
        $womanNoShared->interests()->sync([$i2->id]);

        /** @var DiscoveryService $discovery */
        $discovery = app(DiscoveryService::class);
        $paginator = $discovery->getDiscoverableProfiles($man);

        $ids = collect($paginator->items())->pluck('id');
        $this->assertContains($womanNoShared->id, $ids);
    }

    public function test_16_profile_completeness_calculated_correctly(): void
    {
        $user = $this->createActiveUser(['avatar' => 'https://example.com/avatar.jpg']);
        $user->profile->update(['bio' => 'Hello this is my test bio for completeness!']);

        $score = $user->getCompletenessScore();
        $this->assertGreaterThanOrEqual(50, $score);
    }

    public function test_17_private_financial_fields_are_not_exposed_to_others(): void
    {
        $viewer = $this->createActiveUser([], 'male');
        $target = $this->createActiveUser([], 'female');

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson("/api/v1/profile/{$target->id}");

        $response->assertStatus(200);
        $json = $response->getContent();

        $this->assertStringNotContainsString('wallet_balance', $json);
        $this->assertStringNotContainsString('mpesa_phone', $json);
        $this->assertStringNotContainsString('email@', $json);
    }

    public function test_18_gender_cannot_be_changed_through_profile_api(): void
    {
        $woman = $this->createActiveUser([], 'female');
        $this->assertEquals('female', $woman->profile->gender);

        $this->actingAs($woman, 'sanctum')
            ->putJson('/api/v1/profile', [
                'gender' => 'male',
            ]);

        $this->assertEquals('female', $woman->fresh()->profile->gender);
    }

    public function test_19_country_cannot_be_manually_changed_through_profile_api(): void
    {
        $user = $this->createActiveUser();
        $this->assertEquals('KE', $user->profile->country);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'country' => 'US',
            ]);

        $this->assertEquals('KE', $user->fresh()->profile->country);
    }

    public function test_20_creator_and_level_status_cannot_be_manipulated(): void
    {
        $user = $this->createActiveUser(['is_creator' => false]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/v1/profile', [
                'is_creator' => true,
                'creator_status' => 'approved',
                'level' => 10,
                'credits' => 999999,
                'wallet_balance' => 999999,
            ]);

        $freshUser = $user->fresh();
        $this->assertFalse((bool) $freshUser->is_creator);
        $this->assertEquals('unverified', $freshUser->creator_status);
        $this->assertEquals(500, $freshUser->wallet->credits);
    }
}
