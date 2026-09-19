<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenValidationAndCallThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_3200_tokens_can_use_match(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 3200, description: 'Initial balance 3200');

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create([
            'user_id' => $femaleUser->id,
            'online_status' => 'available',
        ]);

        // Verify wallet balance API returns 3200
        $walletRes = $this->actingAs($maleUser, 'sanctum')->getJson('/api/v1/wallet');
        $walletRes->assertStatus(200)
            ->assertJsonPath('data.balance', 3200);

        // Initiate match
        $matchRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/matches/search');
        $matchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_user.id', $femaleUser->id);

        // Deducted 50 tokens from 3200 -> 3150
        $wallet = $walletService->getWallet($maleUser);
        $this->assertEquals(3150, $wallet->coin_balance);
    }

    public function test_user_with_3100_tokens_can_use_match(): void
    {
        $femaleUser = User::factory()->female()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($femaleUser, 3100, description: 'Initial balance 3100');

        $maleUser = User::factory()->male()->create();
        UserProfile::factory()->create([
            'user_id' => $maleUser->id,
            'online_status' => 'available',
        ]);

        // Verify wallet API match balance consistency
        $walletRes = $this->actingAs($femaleUser, 'sanctum')->getJson('/api/v1/wallet');
        $walletRes->assertStatus(200)
            ->assertJsonPath('data.balance', 3100);

        $matchRes = $this->actingAs($femaleUser, 'sanctum')->postJson('/api/v1/matches/search');
        $matchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.target_user.id', $maleUser->id);

        $wallet = $walletService->getWallet($femaleUser);
        $this->assertEquals(3050, $wallet->coin_balance);
    }

    public function test_insufficient_tokens_rejects_with_402(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 10, description: 'Insufficient setup');

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create([
            'user_id' => $femaleUser->id,
            'online_status' => 'available',
        ]);

        $matchRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/matches/search');
        $matchRes->assertStatus(402)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'INSUFFICIENT_TOKENS');

        // Tokens untouched
        $this->assertEquals(10, $walletService->getWallet($maleUser)->coin_balance);
    }

    public function test_no_tokens_deducted_if_no_eligible_candidate(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 3200, description: 'Balance check');

        // No female users
        $matchRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/matches/search');
        $matchRes->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'NO_MATCH_AVAILABLE');

        $this->assertEquals(3200, $walletService->getWallet($maleUser)->coin_balance);
    }

    public function test_legitimate_direct_video_call_can_be_initiated_without_429(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 3200);

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create([
            'user_id' => $femaleUser->id,
            'online_status' => 'available',
        ]);
        UserProfile::factory()->create([
            'user_id' => $maleUser->id,
            'online_status' => 'available',
        ]);

        $callRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/calls/request', [
            'receiver_id' => $femaleUser->id,
            'call_type' => 'video',
        ]);

        $callRes->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call_session.caller_id', $maleUser->id)
            ->assertJsonPath('data.call_session.receiver_id', $femaleUser->id);

        $this->assertNotNull($callRes->json('data.livekit_token'));
    }

    public function test_same_gender_call_remains_rejected(): void
    {
        $maleUser1 = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser1, 3200);

        $maleUser2 = User::factory()->male()->create();
        UserProfile::factory()->create(['user_id' => $maleUser2->id, 'online_status' => 'available', 'gender' => 'male']);

        $callRes = $this->actingAs($maleUser1, 'sanctum')->postJson('/api/v1/calls/request', [
            'receiver_id' => $maleUser2->id,
            'call_type' => 'video',
        ]);

        $callRes->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'REJECTED_OFFLINE');
    }

    public function test_match_request_does_not_require_receiver_id_payload(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 3200);

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create(['user_id' => $femaleUser->id, 'online_status' => 'available', 'gender' => 'female']);

        // Empty body - server determines candidate
        $matchRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/matches/search', []);
        $matchRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.call_session.caller_id', $maleUser->id)
            ->assertJsonPath('data.call_session.receiver_id', $femaleUser->id);

        $this->assertNotEquals($maleUser->id, $femaleUser->id);
    }

    public function test_receiver_presence_heartbeat_receives_active_call(): void
    {
        $maleUser = User::factory()->male()->create();
        $walletService = app(WalletService::class);
        $walletService->creditCoins($maleUser, 3200);

        $femaleUser = User::factory()->female()->create();
        UserProfile::factory()->create(['user_id' => $femaleUser->id, 'online_status' => 'available', 'gender' => 'female']);

        $matchRes = $this->actingAs($maleUser, 'sanctum')->postJson('/api/v1/matches/search', []);
        $sessionId = $matchRes->json('data.call_session.id');

        // Receiver sends heartbeat and gets active call
        $presenceRes = $this->actingAs($femaleUser, 'sanctum')->postJson('/api/v1/presence/heartbeat');
        $presenceRes->assertStatus(200)
            ->assertJsonPath('data.active_call.id', $sessionId)
            ->assertJsonPath('data.active_call.caller_id', $maleUser->id)
            ->assertJsonPath('data.active_call.receiver_id', $femaleUser->id);

        // Receiver accepts active call session
        $acceptRes = $this->actingAs($femaleUser, 'sanctum')->postJson("/api/v1/calls/{$sessionId}/accept");
        $acceptRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'CONNECTED');

        $this->assertNotNull($acceptRes->json('data.livekit_token'));
    }
}
