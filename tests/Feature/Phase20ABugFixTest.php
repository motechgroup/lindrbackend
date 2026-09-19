<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\CreditLedgerService;
use App\Services\LiveKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase20ABugFixTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_call_returns_lindr_display_username(): void
    {
        $caller = User::factory()->create(['name' => 'Google Account Name']);
        UserProfile::factory()->create([
            'user_id' => $caller->id,
            'display_name' => 'LindrUsername123',
        ]);

        $receiver = User::factory()->create(['name' => 'Receiver Name']);
        UserProfile::factory()->create(['user_id' => $receiver->id]);

        $callSession = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $receiver->id,
            'call_type' => 'video',
            'room_name' => 'room_test_123',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
        ]);

        $response = $this->actingAs($receiver)
            ->getJson('/api/v1/calls/pending');

        $response->assertStatus(200)
            ->assertJsonPath('data.caller_name', 'LindrUsername123')
            ->assertJsonPath('data.id', (string) $callSession->id);
    }

    public function test_livekit_token_serializes_lindr_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Google Original Name']);
        UserProfile::factory()->create([
            'user_id' => $user->id,
            'display_name' => 'LindrSuperStar',
        ]);

        $livekitService = app(LiveKitService::class);
        $result = $livekitService->generateJoinToken($user, 'room_test_livekit');

        $this->assertArrayHasKey('token', $result);
        $this->assertEquals((string) $user->id, $result['identity']);

        // Decode JWT payload
        $parts = explode('.', $result['token']);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        $this->assertEquals('LindrSuperStar', $payload['name']);
    }

    public function test_creator_credits_summary_endpoint_returns_real_data(): void
    {
        $creator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);
        UserProfile::factory()->create(['user_id' => $creator->id]);

        $creditService = app(CreditLedgerService::class);
        $creditService->addCredits($creator, 'CALL_EARNING', 500, 500, 0, 'Video call payout test');

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/credits');

        $response->assertStatus(200)
            ->assertJsonPath('data.is_creator', true)
            ->assertJsonPath('data.available_credits', 500)
            ->assertJsonPath('data.calls_credits', 500);
    }

    public function test_video_liveness_upload_and_verification_submission(): void
    {
        Storage::fake('private');

        $user = User::factory()->create(['creator_status' => 'unverified']);
        UserProfile::factory()->create(['user_id' => $user->id]);

        $fakeVideo = UploadedFile::fake()->create('liveness_video.mp4', 2048, 'video/mp4');

        $response = $this->actingAs($user)
            ->postJson('/api/v1/liveness/verify', [
                'challenge_id' => 'ch_test_video_123',
                'video' => $fakeVideo,
                'gestures_completed' => ['turn_head_left', 'turn_head_right', 'nod_head', 'open_mouth', 'blink'],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertEquals('pending', $user->fresh()->creator_status);
        $this->assertDatabaseHas('liveness_verifications', [
            'user_id' => $user->id,
            'challenge_id' => 'ch_test_video_123',
            'status' => 'pending',
        ]);
    }
}
