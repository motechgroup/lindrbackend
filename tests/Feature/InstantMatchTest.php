<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstantMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_instant_match_requires_sufficient_tokens(): void
    {
        $maleUser = User::factory()->male()->create();
        // Male user has 0 tokens
        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create([
            'user_id' => $femaleUser->id,
            'online_status' => 'available',
        ]);

        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/matches/search');

        $response->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INSUFFICIENT_TOKENS');

        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('call_sessions', 0);
    }

    public function test_instant_match_succeeds_and_debits_tokens(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 100, description: 'Test setup');

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create([
            'user_id' => $femaleUser->id,
            'online_status' => 'available',
        ]);

        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_user.id', $femaleUser->id);

        // Check wallet debited by 50 tokens
        $wallet = $walletService->getWallet($maleUser);
        $this->assertEquals(50, $wallet->coin_balance);

        // Check match and call session records
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('call_sessions', 1);

        // Check both profiles marked busy
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $maleUser->id,
            'online_status' => 'busy',
        ]);
        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $femaleUser->id,
            'online_status' => 'busy',
        ]);
    }

    public function test_instant_match_returns_no_match_available_if_no_user_online(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 100, description: 'Test setup');

        // No female users available

        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NO_MATCH_AVAILABLE');

        // 0 tokens debited if no match found
        $wallet = $walletService->getWallet($maleUser);
        $this->assertEquals(100, $wallet->coin_balance);

        $this->assertDatabaseCount('matches', 0);
        $this->assertDatabaseCount('call_sessions', 0);
    }
}
