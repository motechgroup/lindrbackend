<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\CreditLedgerService;
use App\Services\MonetizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinalBusinessModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformSetting::set('credits_per_usd', '10.0');
        PlatformSetting::set('payout_number_change_hold_hours', '48');
        PlatformSetting::set('chat_female_creator_share_pct', '60.0');
        PlatformSetting::set('chat_male_creator_share_pct', '60.0');
    }

    public function test_google_sign_in_authenticates_or_creates_user(): void
    {
        $response = $this->postJson('/api/v1/auth/google', [
            'google_id' => 'google_test_12345',
            'email' => 'googleuser@example.com',
            'name' => 'Google Test User',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertDatabaseHas('users', [
            'email' => 'googleuser@example.com',
            'provider' => 'google',
            'provider_user_id' => 'google_test_12345',
        ]);
    }

    public function test_liveness_challenge_and_verification_submission(): void
    {
        Storage::fake('private');

        $user = User::factory()->create([
            'is_creator' => false,
            'creator_status' => 'none',
        ]);

        // 1. Get challenge
        $challengeRes = $this->actingAs($user)
            ->getJson('/api/v1/liveness/challenge');

        $challengeRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $challengeId = $challengeRes->json('data.challenge_id');

        // 2. Submit verify selfie
        $file = UploadedFile::fake()->image('selfie.jpg');

        $verifyRes = $this->actingAs($user)
            ->postJson('/api/v1/liveness/verify', [
                'challenge_id' => $challengeId,
                'selfie_image' => $file,
                'gestures_completed' => ['turn_head_left', 'smile'],
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('liveness_verifications', [
            'user_id' => $user->id,
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $user->fresh()->creator_status);
    }

    public function test_gender_neutral_creator_split_and_credit_ledger(): void
    {
        $maleCreator = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);
        UserProfile::factory()->create([
            'user_id' => $maleCreator->id,
            'gender' => 'male',
        ]);
        Wallet::create(['user_id' => $maleCreator->id, 'balance' => 0, 'credits' => 0]);

        $monetizationService = app(MonetizationService::class);
        $split = $monetizationService->calculateSplit(100, 'chat', 'male');

        $this->assertEquals(60, $split['creator_amount']);
        $this->assertEquals(40, $split['platform_amount']);

        $creditService = app(CreditLedgerService::class);
        $creditService->creditCreator(
            $maleCreator,
            60,
            'chat',
            'msg_100',
            'Paid message test'
        );

        $this->assertEquals(60, $maleCreator->fresh()->wallet->credits);

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $maleCreator->id,
            'amount_credits' => 60,
            'transaction_type' => 'CHAT_EARNING',
        ]);
    }

    public function test_mpesa_otp_verification_and_payout_hold(): void
    {
        $user = User::factory()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
            'mpesa_phone' => null,
            'mpesa_phone_verified' => false,
        ]);
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'credits' => 100]);

        // 1. Send OTP
        $sendRes = $this->actingAs($user)
            ->postJson('/api/v1/payouts/mpesa/send-otp', [
                'phone' => '0712345678',
            ]);

        $sendRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $otpCode = $sendRes->json('data.otp_code');
        $this->assertNotEmpty($otpCode);

        // 2. Verify OTP
        $verifyRes = $this->actingAs($user)
            ->postJson('/api/v1/payouts/mpesa/verify-otp', [
                'phone' => '0712345678',
                'code' => $otpCode,
            ]);

        $verifyRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.mpesa_phone_verified', true);

        $freshUser = $user->fresh();
        $this->assertEquals('254712345678', $freshUser->mpesa_phone);
        $this->assertFalse($freshUser->hasActivePayoutHold());

        // 3. Update phone number -> triggers payout hold!
        $sendRes2 = $this->actingAs($freshUser)
            ->postJson('/api/v1/payouts/mpesa/send-otp', [
                'phone' => '0799887766',
            ]);
        $otpCode2 = $sendRes2->json('data.otp_code');

        $this->actingAs($freshUser)
            ->postJson('/api/v1/payouts/mpesa/verify-otp', [
                'phone' => '0799887766',
                'code' => $otpCode2,
            ]);

        $this->assertTrue($freshUser->fresh()->hasActivePayoutHold());
    }
}
