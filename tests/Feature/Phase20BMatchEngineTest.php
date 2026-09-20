<?php

namespace Tests\Feature;

use App\Models\MatchRequest;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\CallService;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase20BMatchEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_start_creates_broadcast_request_and_deducts_tokens(): void
    {
        $initiator = User::factory()->create(['name' => 'Male Initiator', 'role' => 'male']);
        UserProfile::factory()->create(['user_id' => $initiator->id, 'gender' => 'male']);
        Wallet::create(['user_id' => $initiator->id, 'coin_balance' => 100]);

        $response = $this->actingAs($initiator)
            ->postJson('/api/v1/match/start');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'broadcasting')
            ->assertJsonPath('data.code', 'SEARCHING');

        $this->assertEquals(50, Wallet::where('user_id', $initiator->id)->first()->coin_balance);
        $this->assertDatabaseHas('match_requests', [
            'initiator_id' => $initiator->id,
            'status' => 'broadcasting',
        ]);
    }

    public function test_pending_match_requests_returned_to_eligible_opposite_gender_receiver(): void
    {
        $initiator = User::factory()->create(['name' => 'Male Initiator', 'role' => 'male']);
        UserProfile::factory()->create(['user_id' => $initiator->id, 'gender' => 'male', 'display_name' => 'JohnDoe']);
        Wallet::create(['user_id' => $initiator->id, 'coin_balance' => 100]);

        $receiver = User::factory()->create(['name' => 'Female Receiver', 'role' => 'female']);
        UserProfile::factory()->create(['user_id' => $receiver->id, 'gender' => 'female', 'online_status' => 'available']);

        $matchingService = app(MatchingService::class);
        $startRes = $matchingService->startMatchBroadcast($initiator);
        $reqId = $startRes['match_request_id'];

        $response = $this->actingAs($receiver)
            ->getJson('/api/v1/match/pending');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.match_request_id', $reqId)
            ->assertJsonPath('data.0.initiator_name', 'JohnDoe');
    }

    public function test_first_accept_wins_and_second_accept_is_rejected(): void
    {
        $initiator = User::factory()->create(['name' => 'Male Initiator', 'role' => 'male']);
        UserProfile::factory()->create(['user_id' => $initiator->id, 'gender' => 'male']);
        Wallet::create(['user_id' => $initiator->id, 'coin_balance' => 100]);

        $receiverB = User::factory()->create(['name' => 'Female Receiver B', 'role' => 'female']);
        UserProfile::factory()->create(['user_id' => $receiverB->id, 'gender' => 'female', 'online_status' => 'available']);

        $receiverC = User::factory()->create(['name' => 'Female Receiver C', 'role' => 'female']);
        UserProfile::factory()->create(['user_id' => $receiverC->id, 'gender' => 'female', 'online_status' => 'available']);

        $matchingService = app(MatchingService::class);
        $startRes = $matchingService->startMatchBroadcast($initiator);
        $reqId = $startRes['match_request_id'];

        // Receiver B accepts FIRST
        $responseB = $this->actingAs($receiverB)
            ->postJson("/api/v1/match/{$reqId}/accept");

        $responseB->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.code', 'MATCHED');

        $this->assertEquals('matched', MatchRequest::find($reqId)->status);
        $this->assertEquals($receiverB->id, MatchRequest::find($reqId)->matched_user_id);

        // Receiver C attempts to accept LATE
        $responseC = $this->actingAs($receiverC)
            ->postJson("/api/v1/match/{$reqId}/accept");

        $responseC->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'MATCH_NO_LONGER_AVAILABLE');
    }

    public function test_initiator_cancellation_invalidates_match_request(): void
    {
        $initiator = User::factory()->create(['name' => 'Male Initiator', 'role' => 'male']);
        UserProfile::factory()->create(['user_id' => $initiator->id, 'gender' => 'male']);
        Wallet::create(['user_id' => $initiator->id, 'coin_balance' => 100]);

        $matchingService = app(MatchingService::class);
        $startRes = $matchingService->startMatchBroadcast($initiator);
        $reqId = $startRes['match_request_id'];

        $cancelRes = $this->actingAs($initiator)
            ->postJson("/api/v1/match/{$reqId}/cancel");

        $cancelRes->assertStatus(200);
        $this->assertEquals('cancelled', MatchRequest::find($reqId)->status);
    }

    public function test_direct_video_call_flow_remains_unchanged(): void
    {
        $caller = User::factory()->create(['name' => 'Direct Caller', 'role' => 'male']);
        UserProfile::factory()->create(['user_id' => $caller->id, 'gender' => 'male']);
        Wallet::create(['user_id' => $caller->id, 'coin_balance' => 200]);

        $receiver = User::factory()->create(['name' => 'Direct Receiver', 'role' => 'female']);
        UserProfile::factory()->create(['user_id' => $receiver->id, 'gender' => 'female', 'online_status' => 'available']);

        $callService = app(CallService::class);
        $callRes = $callService->requestCall($caller, $receiver, 'video');

        $this->assertEquals('RINGING', $callRes['status']);
        $this->assertEquals($caller->id, $callRes['call_session']->caller_id);
        $this->assertEquals($receiver->id, $callRes['call_session']->receiver_id);
    }
}
