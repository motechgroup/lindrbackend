<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\DiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase19SimplificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $maleUser;

    protected User $femaleUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed');

        $this->maleUser = User::factory()->create([
            'name' => 'MaleTester',
            'role' => 'male',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Wallet::create(['user_id' => $this->maleUser->id, 'coin_balance' => 500]);
        UserProfile::create([
            'user_id' => $this->maleUser->id,
            'display_name' => 'MaleTester',
            'online_status' => 'available',
            'country' => 'Kenya',
            'country_code' => 'KE',
            'date_of_birth' => '1995-05-15',
            'gender' => 'male',
        ]);

        $this->femaleUser = User::factory()->create([
            'name' => 'FemaleTester',
            'role' => 'female',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Wallet::create(['user_id' => $this->femaleUser->id, 'coin_balance' => 0]);
        UserProfile::create([
            'user_id' => $this->femaleUser->id,
            'display_name' => 'FemaleTester',
            'online_status' => 'available',
            'country' => 'Kenya',
            'country_code' => 'KE',
            'date_of_birth' => '1998-08-20',
            'gender' => 'female',
        ]);
    }

    public function test_username_update_succeeds_and_prevents_duplicates(): void
    {
        $response = $this->actingAs($this->maleUser, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'UpdatedMaleUsername',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('UpdatedMaleUsername', $this->maleUser->fresh()->name);
        $this->assertEquals('UpdatedMaleUsername', $this->maleUser->fresh()->profile->display_name);

        // Duplicate username attempt should fail with 422
        $dupResponse = $this->actingAs($this->femaleUser, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'UpdatedMaleUsername',
            ]);

        $dupResponse->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_protected_fields_cannot_be_mutated_via_profile_update(): void
    {
        $this->actingAs($this->maleUser, 'sanctum')
            ->putJson('/api/v1/profile', [
                'is_creator' => true,
                'creator_status' => 'approved',
                'coin_balance' => 999999,
                'country' => 'United States',
            ]);

        $fresh = $this->maleUser->fresh();
        $this->assertFalse((bool) $fresh->is_creator);
        $this->assertNotEquals('approved', $fresh->creator_status);
        $this->assertEquals('Kenya', $fresh->profile->country);
    }

    public function test_profile_completeness_does_not_require_bio_or_interests(): void
    {
        $score = $this->maleUser->getCompletenessScore();

        // User has display name + default setup, score should be >= 30 without bio or interests
        $this->assertGreaterThanOrEqual(30, $score);
        $this->assertTrue($this->maleUser->isOnboarded());
    }

    public function test_discover_excludes_offline_users_and_enforces_opposite_gender(): void
    {
        // Set all female profiles offline
        UserProfile::where('gender', 'female')->update(['online_status' => 'offline']);

        $profiles = app(DiscoveryService::class)->getDiscoverableProfiles($this->maleUser);

        $this->assertEquals(0, $profiles->count());

        // Bring female user online
        $this->femaleUser->profile->update(['online_status' => 'available', 'last_heartbeat_at' => now()]);

        $profilesOnline = app(DiscoveryService::class)->getDiscoverableProfiles($this->maleUser);
        $this->assertGreaterThanOrEqual(1, $profilesOnline->count());
    }

    public function test_liveness_challenge_and_selfie_verification_submission(): void
    {
        Storage::fake('private');

        $challengeRes = $this->actingAs($this->femaleUser, 'sanctum')
            ->getJson('/api/v1/liveness/challenge');

        $challengeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $challengeId = $challengeRes->json('data.challenge_id');

        $file = UploadedFile::fake()->image('selfie.jpg', 500, 500);

        $verifyRes = $this->actingAs($this->femaleUser, 'sanctum')
            ->postJson('/api/v1/liveness/verify', [
                'challenge_id' => $challengeId,
                'selfie_image' => $file,
                'gestures_completed' => ['turn_head_left', 'smile'],
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('pending', $this->femaleUser->fresh()->creator_status);
    }
}
