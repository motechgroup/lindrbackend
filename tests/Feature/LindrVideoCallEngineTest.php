<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\LevelRule;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserProfile;
use App\Services\CallService;
use App\Services\LiveKitService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LindrVideoCallEngineTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected CallService $callService;

    protected LiveKitService $liveKitService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->callService = app(CallService::class);
        $this->liveKitService = app(LiveKitService::class);

        // Ensure Level 0 exists for commission tests
        LevelRule::firstOrCreate([
            'level' => 0,
        ], [
            'name' => 'Starter Level',
            'required_xp' => 0,
            'creator_commission_pct' => 70,
            'benefits' => ['Standard exposure'],
        ]);
    }

    protected function createProfile(User $user, array $attributes = []): UserProfile
    {
        return UserProfile::create(array_merge([
            'user_id' => $user->id,
            'display_name' => 'User_'.$user->id,
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1996-01-01',
            'gender' => 'female',
            'country_code' => 'KE',
            'level' => 0,
        ], $attributes));
    }

    public function test_request_call_success_returns_livekit_token_and_sets_busy_status(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller, ['online_status' => 'available']);
        $this->createProfile($receiver, ['online_status' => 'available']);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
                'call_type' => 'video',
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'call_session_id',
                    'room_name',
                    'rate_per_minute',
                    'status',
                    'livekit_url',
                    'livekit_token',
                ],
            ]);

        $this->assertDatabaseHas('call_sessions', [
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'status' => CallSession::STATUS_RINGING,
            'rate_per_minute' => 20,
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $caller->id,
            'online_status' => 'busy',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $receiver->id,
            'online_status' => 'busy',
        ]);
    }

    public function test_request_call_fails_when_recipient_is_busy(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller, ['online_status' => 'available']);
        $this->createProfile($receiver, ['online_status' => 'busy']);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'REJECTED_BUSY',
                'tokens_deducted' => 0,
            ]);
    }

    public function test_request_call_fails_when_recipient_is_offline(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller, ['online_status' => 'available']);
        $this->createProfile($receiver, ['online_status' => 'offline']);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'REJECTED_OFFLINE',
                'tokens_deducted' => 0,
            ]);
    }

    public function test_request_call_fails_when_user_is_blocked(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller, ['online_status' => 'available']);
        $this->createProfile($receiver, ['online_status' => 'available']);

        UserBlock::create([
            'blocker_id' => $receiver->id,
            'blocked_id' => $caller->id,
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'REJECTED_OFFLINE',
                'tokens_deducted' => 0,
            ]);
    }

    public function test_request_call_fails_with_insufficient_tokens(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        // 5 tokens is less than rate per minute
        $this->walletService->creditCoins($caller, 5);
        $this->createProfile($caller, ['online_status' => 'available']);
        $this->createProfile($receiver, ['online_status' => 'available']);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'INSUFFICIENT_TOKENS',
                'tokens_deducted' => 0,
            ]);
    }

    public function test_accept_call_returns_livekit_token_and_sets_connected_status(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller, ['online_status' => 'busy']);
        $this->createProfile($receiver, ['online_status' => 'busy']);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_123',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
            'started_at' => now(),
        ]);

        $response = $this->actingAs($receiver, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'call_session' => [
                        'status' => CallSession::STATUS_CONNECTED,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('call_sessions', [
            'id' => $session->id,
            'status' => CallSession::STATUS_CONNECTED,
        ]);
    }

    public function test_accept_call_unauthorized_for_non_receiver(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();
        $stranger = User::factory()->create();

        $this->createProfile($caller);
        $this->createProfile($receiver);
        $this->createProfile($stranger);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_456',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
        ]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/accept");

        $response->assertStatus(403);
    }

    public function test_decline_call_updates_status_and_reverts_presence(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->createProfile($caller, ['online_status' => 'busy']);
        $this->createProfile($receiver, ['online_status' => 'busy']);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_decline',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
        ]);

        $response = $this->actingAs($receiver, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/decline");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => CallSession::STATUS_CANCELLED,
                ],
            ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $caller->id,
            'online_status' => 'available',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $receiver->id,
            'online_status' => 'available',
        ]);
    }

    public function test_ping_call_bills_interval_and_allocates_creator_credits(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller);
        $this->createProfile($receiver, ['level' => 0]);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_billing',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'connected_at' => now(),
            'creator_commission_pct' => 70,
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/ping", [
                'minute_number' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'billed' => true,
                    'minute' => 1,
                    'rate' => 20,
                    'creator_credits' => 14,
                    'platform_share' => 6,
                ],
            ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $caller->id,
            'coin_balance' => 80,
        ]);

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $receiver->id,
            'amount_credits' => 14,
            'transaction_type' => 'CALL_EARNING',
        ]);
    }

    public function test_ping_call_unverified_creator_receives_zero_credits(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create([
            'is_creator' => false,
            'creator_status' => 'unverified',
        ]);

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller);
        $this->createProfile($receiver);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_unverified',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'connected_at' => now(),
            'creator_commission_pct' => 70,
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/ping", [
                'minute_number' => 1,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'billed' => true,
                    'minute' => 1,
                    'rate' => 20,
                    'creator_credits' => 0,
                    'platform_share' => 20,
                ],
            ]);

        $this->assertDatabaseMissing('creator_credit_ledgers', [
            'user_id' => $receiver->id,
        ]);
    }

    public function test_ping_call_is_idempotent(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);
        $this->createProfile($caller);
        $this->createProfile($receiver);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_idempotent',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'connected_at' => now(),
            'creator_commission_pct' => 70,
        ]);

        // First ping for minute 1
        $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/ping", ['minute_number' => 1]);

        // Second ping for minute 1 (duplicate)
        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/ping", ['minute_number' => 1]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'billed' => true,
                    'minute' => 1,
                    'idempotent_skip' => true,
                ],
            ]);

        $this->assertDatabaseHas('wallets', [
            'user_id' => $caller->id,
            'coin_balance' => 80,
        ]);
    }

    public function test_ping_call_terminates_call_if_caller_runs_out_of_tokens(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        // 10 tokens is less than 20 rate per minute
        $this->walletService->creditCoins($caller, 10);
        $this->createProfile($caller);
        $this->createProfile($receiver);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_insufficient',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'connected_at' => now(),
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/ping", ['minute_number' => 1]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'billed' => false,
                    'reason' => 'INSUFFICIENT_TOKENS',
                    'call_ended' => true,
                ],
            ]);
    }

    public function test_end_call_updates_status_and_restores_presence(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->createProfile($caller, ['online_status' => 'busy']);
        $this->createProfile($receiver, ['online_status' => 'busy']);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_end',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'started_at' => now()->subMinutes(2),
            'connected_at' => now()->subMinutes(2),
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$session->id}/end", [
                'reason' => 'USER_DISCONNECTED',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => CallSession::STATUS_ENDED,
                    'end_reason' => 'USER_DISCONNECTED',
                ],
            ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $caller->id,
            'online_status' => 'available',
        ]);
    }

    public function test_reconcile_stale_sessions_cancels_idle_ringing_calls(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->createProfile($caller, ['online_status' => 'busy']);
        $this->createProfile($receiver, ['online_status' => 'busy']);

        $session = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_stale',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
        ]);

        CallSession::where('id', $session->id)->update([
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $reconciledCount = $this->callService->reconcileStaleSessions();
        $this->assertEquals(1, $reconciledCount);

        $this->assertDatabaseHas('call_sessions', [
            'id' => $session->id,
            'status' => CallSession::STATUS_CANCELLED,
            'end_reason' => 'TIMEOUT_RECONCILED',
        ]);

        $this->assertDatabaseHas('user_profiles', [
            'user_id' => $caller->id,
            'online_status' => 'available',
        ]);
    }

    public function test_call_history_returns_user_sessions(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->createProfile($caller);
        $this->createProfile($receiver);

        CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_history_1',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_ENDED,
            'duration_seconds' => 120,
            'coins_charged' => 40,
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->getJson('/api/v1/calls/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data',
                    'current_page',
                ],
            ]);
    }
}
