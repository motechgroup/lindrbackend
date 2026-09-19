<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use App\Services\CallService;
use App\Services\LiveKitService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveKitPreparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_livekit_token_can_be_generated_for_user(): void
    {
        $user = User::factory()->create(['name' => 'John Live']);
        /** @var LiveKitService $liveKitService */
        $liveKitService = app(LiveKitService::class);

        $result = $liveKitService->generateRoomToken($user, 'room_alpha');

        $this->assertNotEmpty($result['token']);
        $this->assertCount(3, explode('.', $result['token']));
    }

    public function test_call_session_initiation_and_billing_reconciliation(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();

        UserProfile::create(['user_id' => $male->id, 'display_name' => 'Male', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1995-01-01', 'gender' => 'male']);
        UserProfile::create(['user_id' => $female->id, 'display_name' => 'Female', 'online_status' => 'available', 'last_heartbeat_at' => now(), 'date_of_birth' => '1996-01-01', 'gender' => 'female']);

        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);
        $walletService->creditCoins($male, 100);

        /** @var CallService $callService */
        $callService = app(CallService::class);

        // Request call session
        $result = $callService->requestCall($male, $female, 'audio', 20);

        $this->assertEquals('RINGING', $result['status']);
        $this->assertEquals(20, $result['rate_per_minute']);

        $session = $result['call_session'];

        // Accept and bill 3 minutes
        $callService->acceptCall($session, $female);
        $callService->billCallInterval($session, 2);
        $callService->billCallInterval($session, 3);

        $endedSession = $callService->endCall($session, 'USER_DISCONNECTED');

        $this->assertEquals('ENDED', $endedSession->status);
        $this->assertEquals(60, $endedSession->coins_charged);

        // Male wallet balance reduced from 100 to 40
        $this->assertEquals(40, $male->fresh()->wallet->coin_balance);
    }
}
