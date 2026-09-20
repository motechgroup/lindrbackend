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
            ->assertJsonPath('data.status', 'broadcasting');

        // Check wallet debited by 50 tokens (matching_token_cost)
        $wallet = $walletService->getWallet($maleUser);
        $this->assertEquals(50, $wallet->coin_balance);

        $requestId = $response->json('data.match_request_id');

        // Receiver accepts match
        $acceptRes = $this->actingAs($femaleUser, 'sanctum')
            ->postJson("/api/v1/match/{$requestId}/accept");

        $acceptRes->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check match and call session records
        $this->assertDatabaseCount('matches', 1);
        $this->assertDatabaseCount('call_sessions', 1);
    }

    public function test_instant_match_returns_broadcasting_status_when_search_started(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 100, description: 'Test setup');

        $response = $this->actingAs($maleUser, 'sanctum')
            ->postJson('/api/v1/matches/search');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'broadcasting');

        $wallet = $walletService->getWallet($maleUser);
        $this->assertEquals(50, $wallet->coin_balance);
    }
}
