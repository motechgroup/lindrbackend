<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\CreditLedgerService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MpesaB2CPayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::set('minimum_withdrawal_credits', 100);
        PlatformSetting::set('credits_per_usd', 10.0);
    }

    protected function createVerifiedCreator(array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'is_creator' => true,
            'creator_status' => 'approved',
            'liveness_verified_at' => now(),
            'mpesa_phone' => '254712345678',
            'mpesa_phone_verified' => true,
            'payout_hold_until' => null,
        ], $attributes));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => explode(' ', $user->name)[0],
                'gender' => 'female',
            ]
        );

        /** @var CreditLedgerService $creditService */
        $creditService = app(CreditLedgerService::class);
        $creditService->addCredits($user, 'CALL_EARNING', 1000, 1000, 0, 'Initial test credits');

        return $user->fresh();
    }

    public function test_withdrawal_request_initiates_b2c_payout_and_sets_processing_status(): void
    {
        $creator = $this->createVerifiedCreator();

        $response = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', [
                'credits' => 200,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'processing');

        $withdrawal = Withdrawal::where('user_id', $creator->id)->first();
        $this->assertNotNull($withdrawal);
        $this->assertEquals(WithdrawalStatus::Processing, $withdrawal->status);
        $this->assertNotEmpty($withdrawal->provider_reference);

        // Wallet balance should be 1000 - 200 = 800
        $this->assertEquals(800, Wallet::where('user_id', $creator->id)->value('credits'));
    }

    public function test_b2c_success_callback_marks_withdrawal_paid(): void
    {
        $creator = $this->createVerifiedCreator();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 300);

        // Simulate Safaricom B2C Success callback (ResultCode: 0)
        $callbackPayload = [
            'Result' => [
                'ResultType' => 0,
                'ResultCode' => 0,
                'ResultDesc' => 'The service request has been processed successfully.',
                'OriginatorConversationID' => (string) $withdrawal->id,
                'ConversationID' => $withdrawal->provider_reference ?? 'AG_TEST_123',
                'TransactionID' => 'LKJ99887766',
                'ResultParameters' => [
                    'ResultParameter' => [
                        ['Key' => 'TransactionAmount', 'Value' => 3900],
                        ['Key' => 'TransactionReceipt', 'Value' => 'LKJ99887766'],
                    ],
                ],
            ],
        ];

        $res = $this->postJson('/api/v1/webhooks/mpesa/b2c/result', $callbackPayload);
        $res->assertStatus(200)
            ->assertJsonPath('ResultCode', 0);

        $freshWithdrawal = $withdrawal->fresh();
        $this->assertEquals(WithdrawalStatus::Paid, $freshWithdrawal->status);
        $this->assertEquals('LKJ99887766', $freshWithdrawal->provider_reference);
    }

    public function test_b2c_failure_callback_marks_failed_and_restores_creator_credits(): void
    {
        $creator = $this->createVerifiedCreator();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 400);

        // Wallet balance reduced to 600
        $this->assertEquals(600, Wallet::where('user_id', $creator->id)->value('credits'));

        // Simulate Safaricom B2C Failure callback (ResultCode: 2001 - Invalid phone number)
        $callbackPayload = [
            'Result' => [
                'ResultType' => 0,
                'ResultCode' => 2001,
                'ResultDesc' => 'The initiator information is invalid.',
                'OriginatorConversationID' => (string) $withdrawal->id,
                'ConversationID' => $withdrawal->provider_reference ?? 'AG_TEST_FAIL',
            ],
        ];

        $res = $this->postJson('/api/v1/webhooks/mpesa/b2c/result', $callbackPayload);
        $res->assertStatus(200);

        $freshWithdrawal = $withdrawal->fresh();
        $this->assertEquals(WithdrawalStatus::Failed, $freshWithdrawal->status);
        $this->assertStringContainsString('invalid', $freshWithdrawal->failure_reason);

        // Wallet balance restored to 1000
        $this->assertEquals(1000, Wallet::where('user_id', $creator->id)->value('credits'));

        // WITHDRAWAL_REVERSAL entry logged in ledger
        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $creator->id,
            'transaction_type' => 'WITHDRAWAL_REVERSAL',
            'amount_credits' => 400,
            'reference_id' => (string) $withdrawal->id,
        ]);
    }

    public function test_duplicate_b2c_callback_is_idempotent(): void
    {
        $creator = $this->createVerifiedCreator();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 200);

        $callbackPayload = [
            'Result' => [
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'OriginatorConversationID' => (string) $withdrawal->id,
                'TransactionID' => 'RCPT_112233',
            ],
        ];

        // First callback
        $res1 = $this->postJson('/api/v1/webhooks/mpesa/b2c/result', $callbackPayload);
        $res1->assertStatus(200);

        // Second callback (duplicate)
        $res2 = $this->postJson('/api/v1/webhooks/mpesa/b2c/result', $callbackPayload);
        $res2->assertStatus(200)
            ->assertJsonPath('data.status', 'already_processed');

        // Balance remains unchanged (800)
        $this->assertEquals(800, Wallet::where('user_id', $creator->id)->value('credits'));
    }

    public function test_b2c_timeout_callback_logs_without_double_refunding(): void
    {
        $creator = $this->createVerifiedCreator();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 200);

        $timeoutPayload = [
            'Result' => [
                'ResultCode' => 1,
                'ResultDesc' => 'The request timed out.',
                'OriginatorConversationID' => (string) $withdrawal->id,
            ],
        ];

        $res = $this->postJson('/api/v1/webhooks/mpesa/b2c/timeout', $timeoutPayload);
        $res->assertStatus(200)
            ->assertJsonPath('data.status', 'timeout_logged');

        // Status remains Processing (not failed/reversed)
        $this->assertEquals(WithdrawalStatus::Processing, $withdrawal->fresh()->status);
        $this->assertStringContainsString('timed out', $withdrawal->fresh()->admin_notes);
    }

    public function test_security_response_contains_no_sensitive_mpesa_credentials(): void
    {
        $creator = $this->createVerifiedCreator();

        $res = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 200]);

        $content = $res->getContent();

        $this->assertStringNotContainsString('MPESA_CONSUMER_KEY', $content);
        $this->assertStringNotContainsString('MPESA_CONSUMER_SECRET', $content);
        $this->assertStringNotContainsString('MPESA_PASSKEY', $content);
        $this->assertStringNotContainsString('MPESA_INITIATOR_PASSWORD', $content);
        $this->assertStringNotContainsString('access_token', $content);
    }
}
