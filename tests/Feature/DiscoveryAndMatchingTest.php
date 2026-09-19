<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscoveryAndMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_single_like_does_not_create_match(): void
    {
        $maleUser = User::factory()->male()->create();
        $femaleUser = User::factory()->female()->create();

        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/likes', [
                'target_user_id' => $femaleUser->id,
                'is_like' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matched', false);

        $this->assertDatabaseHas('likes', [
            'user_id' => $maleUser->id,
            'target_user_id' => $femaleUser->id,
            'is_like' => true,
        ]);

        $this->assertDatabaseCount('matches', 0);
    }

    public function test_mutual_like_creates_match(): void
    {
        $maleUser = User::factory()->male()->create();
        $femaleUser = User::factory()->female()->create();

        // Female user likes male user first
        $this->actingAs($femaleUser, 'sanctum')
            ->postJson('/api/v1/likes', [
                'target_user_id' => $maleUser->id,
                'is_like' => true,
            ]);

        // Male user likes female user back
        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/likes', [
                'target_user_id' => $femaleUser->id,
                'is_like' => true,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.matched', true);

        $userLowId = min($maleUser->id, $femaleUser->id);
        $userHighId = max($maleUser->id, $femaleUser->id);

        $this->assertDatabaseHas('matches', [
            'user_low_id' => $userLowId,
            'user_high_id' => $userHighId,
        ]);

        $this->assertDatabaseCount('matches', 1);
    }

    public function test_duplicate_mutual_likes_do_not_create_duplicate_match_records(): void
    {
        $maleUser = User::factory()->male()->create();
        $femaleUser = User::factory()->female()->create();

        // Female likes male
        $this->actingAs($femaleUser, 'sanctum')
            ->postJson('/api/v1/likes', ['target_user_id' => $maleUser->id, 'is_like' => true]);

        // Male likes female
        $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/likes', ['target_user_id' => $femaleUser->id, 'is_like' => true]);

        // Male likes female again
        $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/likes', ['target_user_id' => $femaleUser->id, 'is_like' => true]);

        $this->assertDatabaseCount('matches', 1);
    }

    public function test_discovery_feed_filters_and_excludes_swiped_users(): void
    {
        $maleUser = User::factory()->male()->create();
        $female1 = User::factory()->female()->create();
        $female2 = User::factory()->female()->create();

        UserProfile::factory()->create(['user_id' => $female1->id, 'city' => 'Nairobi']);
        UserProfile::factory()->create(['user_id' => $female2->id, 'city' => 'Mombasa']);

        // Swiped female1 already
        $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/likes', ['target_user_id' => $female1->id, 'is_like' => true]);

        // Get discovery feed
        $response = $this->actingAs($maleUser, 'sanctum')
            ->getJson('/api/v1/discovery');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($female1->id, $returnedIds);
        $this->assertContains($female2->id, $returnedIds);
        $this->assertNotContains($maleUser->id, $returnedIds);
    }
}
