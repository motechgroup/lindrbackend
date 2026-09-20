<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\CreatorCreditLedger;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PresenceService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CallPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected PresenceService $presenceService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->presenceService = app(PresenceService::class);
    }

    public function test_available_user_can_receive_a_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        // Setup caller wallet with 100 tokens
        $this->walletService->creditCoins($caller, 100);

        // Receiver is active and available
        UserProfile::create([
            'user_id' => $receiver->id,
            'display_name' => 'Receiver',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
            'country_code' => 'KE',
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1995-08-20',
            'gender' => 'male',
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
                'call_type' => 'video',
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'RINGING');

        // Token balance remains 100 on initial call request (interval billing starts on connection)
        $this->assertEquals(100, $caller->fresh()->wallet->coin_balance);

        // Verify presence transitioned to busy
        $this->assertEquals('busy', $receiver->fresh()->profile->online_status);
        $this->assertEquals('busy', $caller->fresh()->profile->online_status);
    }

    public function test_busy_user_cannot_receive_another_call(): void
    {
        $caller = User::factory()->create();
        $busyReceiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create([
            'user_id' => $busyReceiver->id,
            'display_name' => 'Jane',
            'online_status' => 'busy',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $busyReceiver->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'REJECTED_BUSY')
            ->assertJsonPath('tokens_deducted', 0);
    }

    public function test_offline_user_cannot_receive_a_call(): void
    {
        $caller = User::factory()->create();
        $offlineReceiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create([
            'user_id' => $offlineReceiver->id,
            'display_name' => 'OfflineUser',
            'online_status' => 'offline',
            'last_heartbeat_at' => now()->subHours(5),
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $offlineReceiver->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'REJECTED_OFFLINE')
            ->assertJsonPath('tokens_deducted', 0);
    }

    public function test_busy_call_attempt_does_not_deduct_tokens(): void
    {
        $caller = User::factory()->create();
        $busyReceiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create([
            'user_id' => $busyReceiver->id,
            'display_name' => 'BusyReceiver',
            'online_status' => 'busy',
            'last_heartbeat_at' => now(),
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $busyReceiver->id,
                'rate_per_minute' => 20,
            ]);

        // Balance remains 100
        $this->assertEquals(100, $caller->fresh()->wallet->coin_balance);
        $this->assertEquals(0, WalletTransaction::where('user_id', $caller->id)->where('transaction_type', 'debit')->count());
    }

    public function test_busy_call_attempt_does_not_create_creator_earnings(): void
    {
        $caller = User::factory()->create();
        $creator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create([
            'user_id' => $creator->id,
            'display_name' => 'Creator',
            'online_status' => 'busy',
            'last_heartbeat_at' => now(),
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $creator->id,
                'rate_per_minute' => 20,
            ]);

        $this->assertEquals(0, CreatorCreditLedger::where('user_id', $creator->id)->count());
    }

    public function test_only_one_simultaneous_caller_can_reserve_the_same_recipient(): void
    {
        $callerOne = User::factory()->create();
        $callerTwo = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($callerOne, 100);
        $this->walletService->creditCoins($callerTwo, 100);

        UserProfile::create([
            'user_id' => $receiver->id,
            'display_name' => 'Receiver',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
        ]);

        UserProfile::create(['user_id' => $callerOne->id, 'display_name' => 'Caller1', 'online_status' => 'available', 'last_heartbeat_at' => now()]);
        UserProfile::create(['user_id' => $callerTwo->id, 'display_name' => 'Caller2', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        // First call succeeds
        $res1 = $this->actingAs($callerOne, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $receiver->id]);

        $res1->assertStatus(201);

        // Second call fails with REJECTED_BUSY
        $res2 = $this->actingAs($callerTwo, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $receiver->id]);

        $res2->assertStatus(409)
            ->assertJsonPath('error_code', 'REJECTED_BUSY');

        // Both callers remain at 100 on initial request (interval billing charges when connected/pinged)
        $this->assertEquals(100, $callerOne->fresh()->wallet->coin_balance);
        $this->assertEquals(100, $callerTwo->fresh()->wallet->coin_balance);
    }

    public function test_recipient_becomes_busy_when_call_starts(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create(['user_id' => $receiver->id, 'display_name' => 'Receiver', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1998-05-15', 'gender' => 'female']);
        UserProfile::create(['user_id' => $caller->id, 'display_name' => 'Caller', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $receiver->id]);

        $this->assertEquals('busy', $receiver->fresh()->profile->online_status);
    }

    public function test_recipient_becomes_available_after_call_ends(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create(['user_id' => $receiver->id, 'display_name' => 'Receiver', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1998-05-15', 'gender' => 'female']);
        UserProfile::create(['user_id' => $caller->id, 'display_name' => 'Caller', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        $res = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', ['receiver_id' => $receiver->id]);

        $callId = $res->json('data.call_session_id') ?? $res->json('data.id') ?? $res->json('data.call_session.id');

        $endRes = $this->actingAs($caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/end");

        $endRes->assertStatus(200);

        $this->assertEquals('available', $receiver->fresh()->profile->online_status);
        $this->assertEquals('available', $caller->fresh()->profile->online_status);
    }

    public function test_presence_expires_after_heartbeat_timeout(): void
    {
        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => 'User',
            'online_status' => 'available',
            'last_heartbeat_at' => now()->subMinutes(5),
        ]);

        $presence = $this->presenceService->getUserPresence($user);

        $this->assertEquals('offline', $presence['status']);
        $this->assertFalse($presence['is_available']);
    }

    public function test_discover_returns_correct_country_code(): void
    {
        $user = User::factory()->female()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'display_name' => 'KenyaUser',
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
            'country_code' => 'KE',
            'country' => 'Kenya',
            'online_status' => 'available',
        ]);

        $viewer = User::factory()->male()->create();
        UserProfile::create(['user_id' => $viewer->id, 'display_name' => 'Viewer', 'date_of_birth' => '1995-05-15', 'gender' => 'male']);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/discover');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.profile.country_code', 'KE');
    }

    public function test_discover_does_not_fabricate_active_users(): void
    {
        $viewer = User::factory()->male()->create();
        UserProfile::create(['user_id' => $viewer->id, 'display_name' => 'Viewer', 'date_of_birth' => '1995-05-15', 'gender' => 'male']);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/v1/discover');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthorized_users_cannot_manipulate_another_users_presence(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // User A trying to update presence via heartbeat only affects User A
        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/presence/heartbeat');

        $this->assertNull($userB->fresh()->profile?->last_heartbeat_at);
    }

    public function test_insufficient_caller_tokens_cannot_initiate_a_paid_call(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        // Caller has 5 tokens, rate is 20 tokens
        $this->walletService->creditCoins($caller, 5);

        UserProfile::create(['user_id' => $receiver->id, 'display_name' => 'Receiver', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1998-05-15', 'gender' => 'female']);
        UserProfile::create(['user_id' => $caller->id, 'display_name' => 'Caller', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error_code', 'INSUFFICIENT_TOKENS');
    }

    public function test_verified_creator_earnings_continue_using_configured_split(): void
    {
        $caller = User::factory()->create();
        $creator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create(['user_id' => $creator->id, 'display_name' => 'Creator', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1998-05-15', 'gender' => 'female']);
        UserProfile::create(['user_id' => $caller->id, 'display_name' => 'Caller', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $creator->id,
                'rate_per_minute' => 100,
            ]);

        $response->assertStatus(201);
        $callId = $response->json('data.call_session_id');

        // Accept call to connect and bill interval 1
        $this->actingAs($creator, 'sanctum')
            ->postJson("/api/v1/calls/{$callId}/accept");

        // Creator receives share of 100 tokens
        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $creator->id,
            'transaction_type' => 'CALL_EARNING',
        ]);
    }

    public function test_unverified_creator_cannot_earn_from_calls(): void
    {
        $caller = User::factory()->create();
        $unverifiedCreator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'pending',
        ]);

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create(['user_id' => $unverifiedCreator->id, 'display_name' => 'Creator', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1998-05-15', 'gender' => 'female']);
        UserProfile::create(['user_id' => $caller->id, 'display_name' => 'Caller', 'online_status' => 'available', 'last_heartbeat_at' => now()]);

        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $unverifiedCreator->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(201);

        // Caller is 100 tokens on request
        $this->assertEquals(100, $caller->fresh()->wallet->coin_balance);

        // Unverified creator receives zero credit ledger entries
        $this->assertEquals(0, CreatorCreditLedger::where('user_id', $unverifiedCreator->id)->count());
    }

    public function test_stale_call_session_is_automatically_reconciled_so_user_is_not_permanently_busy(): void
    {
        $caller = User::factory()->create();
        $receiver = User::factory()->create();

        $this->walletService->creditCoins($caller, 100);

        UserProfile::create([
            'user_id' => $receiver->id,
            'display_name' => 'Receiver',
            'online_status' => 'busy',
            'last_heartbeat_at' => now(),
            'date_of_birth' => '1998-05-15',
            'gender' => 'female',
        ]);

        UserProfile::create([
            'user_id' => $caller->id,
            'display_name' => 'Caller',
            'online_status' => 'available',
            'last_heartbeat_at' => now(),
        ]);

        // Create a stale call session updated 5 minutes ago with ended_at = null
        $staleCall = CallSession::create([
            'id' => (string) Str::uuid(),
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'stale_room_123',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_CONNECTED,
            'started_at' => now()->subMinutes(10),
            'connected_at' => now()->subMinutes(10),
            'ended_at' => null,
        ]);

        DB::table('call_sessions')->where('id', $staleCall->id)->update([
            'updated_at' => now()->subMinutes(5),
            'connected_at' => now()->subMinutes(5),
        ]);

        // User presence check auto-reconciles the stale call
        $presence = $this->presenceService->getUserPresence($receiver);

        $this->assertEquals('online', $presence['status']);
        $this->assertTrue($presence['is_available']);
        $this->assertEquals('available', $receiver->fresh()->profile->online_status);
        $this->assertEquals(CallSession::STATUS_CANCELLED, $staleCall->fresh()->status);
        $this->assertEquals('TIMEOUT_RECONCILED', $staleCall->fresh()->end_reason);

        // New call to receiver now succeeds without throwing CallBusyException
        $response = $this->actingAs($caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $receiver->id,
                'rate_per_minute' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
