<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\CallSession;
use App\Models\CreatorCreditLedger;
use App\Models\Gift;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\CallService;
use App\Services\ChatService;
use App\Services\CreditLedgerService;
use App\Services\GiftService;
use App\Services\MonetizationService;
use App\Services\MpesaService;
use App\Services\PaidMessagingService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorMonetizationAndWithdrawalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PlatformSetting::set('credits_per_usd', 10.0);
        PlatformSetting::set('minimum_withdrawal_credits', 100);
        PlatformSetting::set('video_call_rate_per_minute', 30);
        PlatformSetting::set('message_cost', 5);
        PlatformSetting::set('call_female_creator_share_pct', 60.0);
        PlatformSetting::set('call_male_creator_share_pct', 60.0);
        PlatformSetting::set('gift_female_creator_share_pct', 60.0);
        PlatformSetting::set('gift_male_creator_share_pct', 60.0);
        PlatformSetting::set('chat_female_creator_share_pct', 60.0);
        PlatformSetting::set('chat_male_creator_share_pct', 60.0);
    }

    protected function createVerifiedCreator(string $gender = 'female', array $attributes = []): User
    {
        $role = strtolower($gender) === 'male' ? UserRole::Male : UserRole::Female;

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'role' => $role,
            'is_creator' => true,
            'creator_status' => 'approved',
            'mpesa_phone' => '254712345678',
            'mpesa_phone_verified' => true,
        ], $attributes));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => explode(' ', $user->name)[0],
                'gender' => $gender,
                'country' => 'KE',
                'country_code' => 'KE',
                'date_of_birth' => '1996-06-20',
                'online_status' => 'available',
                'last_heartbeat_at' => now(),
            ]
        );

        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['coin_balance' => 500, 'balance' => 500, 'credits' => 1000]
        );

        return $user->fresh(['profile', 'wallet']);
    }

    protected function createNormalUser(string $gender = 'male', array $attributes = []): User
    {
        $role = strtolower($gender) === 'female' ? UserRole::Female : UserRole::Male;

        $user = User::factory()->create(array_merge([
            'status' => UserStatus::Active,
            'role' => $role,
            'is_creator' => false,
            'creator_status' => 'unverified',
        ], $attributes));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => explode(' ', $user->name)[0],
                'gender' => $gender,
                'country' => 'KE',
                'country_code' => 'KE',
                'date_of_birth' => '1998-03-10',
                'online_status' => 'available',
                'last_heartbeat_at' => now(),
            ]
        );

        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['coin_balance' => 1000, 'balance' => 1000, 'credits' => 0]
        );

        return $user->fresh(['profile', 'wallet']);
    }

    public function test_1_verified_male_and_female_creators_can_earn_credits(): void
    {
        $femaleCreator = $this->createVerifiedCreator('female');
        $maleCreator = $this->createVerifiedCreator('male');

        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $entryF = $ledgerService->creditCreator($femaleCreator, 18.0, 'call', 'test_f_1', 'Call earning');
        $entryM = $ledgerService->creditCreator($maleCreator, 18.0, 'call', 'test_m_1', 'Call earning');

        $this->assertEquals(1018, $femaleCreator->fresh()->wallet->credits);
        $this->assertEquals(1018, $maleCreator->fresh()->wallet->credits);
        $this->assertEquals('CALL_EARNING', $entryF->transaction_type);
        $this->assertEquals('CALL_EARNING', $entryM->transaction_type);
    }

    public function test_2_unverified_creators_receive_zero_creator_credits(): void
    {
        $unverifiedUser = $this->createNormalUser('female');

        /** @var MonetizationService $monetization */
        $monetization = app(MonetizationService::class);

        $split = $monetization->calculateSplit(30, 'call', 'female', $unverifiedUser);

        $this->assertEquals(0, $split['creator_amount']);
        $this->assertEquals(30, $split['platform_amount']);
        $this->assertEquals(0.0, $split['creator_share_pct']);
    }

    public function test_3_suspended_and_banned_creators_cannot_earn_or_withdraw(): void
    {
        $suspendedCreator = $this->createVerifiedCreator('female', ['status' => UserStatus::Suspended]);
        $bannedCreator = $this->createVerifiedCreator('female', ['status' => UserStatus::Banned]);

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $this->expectException(\InvalidArgumentException::class);
        $withdrawalService->requestWithdrawal($suspendedCreator, 200);
    }

    public function test_4_match_fee_is_100_percent_lindr_revenue_without_creator_credits(): void
    {
        /** @var MonetizationService $monetization */
        $monetization = app(MonetizationService::class);

        $split = $monetization->calculateMatchingSplit();

        $this->assertEquals(50, $split['gross_tokens']);
        $this->assertEquals(0.00, $split['creator_credits']);
        $this->assertEquals(50, $split['platform_share_tokens']);
    }

    public function test_5_call_session_minute_billing_credits_verified_creator_with_commission(): void
    {
        $caller = $this->createNormalUser('male');
        $creator = $this->createVerifiedCreator('female');

        $callSession = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $creator->id,
            'status' => CallSession::STATUS_CONNECTED,
            'rate_per_minute' => 30,
            'coins_charged' => 0,
            'creator_credits_earned' => 0,
            'room_name' => 'room_test_123',
        ]);

        /** @var CallService $callService */
        $callService = app(CallService::class);

        $result = $callService->billCallInterval($callSession, 1);

        $this->assertTrue($result['billed']);
        $this->assertEquals(18, $result['creator_credits']);
        $this->assertEquals(12, $result['platform_share']);
        $this->assertEquals(970, $caller->fresh()->wallet->coin_balance);
        $this->assertEquals(1018, $creator->fresh()->wallet->credits);
    }

    public function test_6_call_session_billing_is_idempotent(): void
    {
        $caller = $this->createNormalUser('male');
        $creator = $this->createVerifiedCreator('female');

        $callSession = CallSession::create([
            'caller_id' => $caller->id,
            'receiver_id' => $creator->id,
            'status' => CallSession::STATUS_CONNECTED,
            'rate_per_minute' => 30,
            'coins_charged' => 0,
            'room_name' => 'room_test_idem_1',
        ]);

        /** @var CallService $callService */
        $callService = app(CallService::class);

        $res1 = $callService->billCallInterval($callSession, 1);
        $res2 = $callService->billCallInterval($callSession, 1);

        $this->assertTrue($res1['billed']);
        $this->assertTrue($res2['billed']);
        $this->assertTrue($res2['idempotent_skip'] ?? false);
        $this->assertEquals(1018, $creator->fresh()->wallet->credits);
    }

    public function test_7_gift_sending_credits_verified_creator(): void
    {
        $sender = $this->createNormalUser('male');
        $recipient = $this->createVerifiedCreator('female');

        $gift = Gift::create([
            'name' => 'Rose',
            'coin_price' => 100,
            'coin_cost' => 100,
            'is_active' => true,
        ]);

        /** @var GiftService $giftService */
        $giftService = app(GiftService::class);

        $tx = $giftService->sendGift($sender, $recipient, $gift);

        $this->assertEquals('completed', $tx->status);
        $this->assertEquals(900, $sender->fresh()->wallet->coin_balance);
        $this->assertEquals(1060, $recipient->fresh()->wallet->credits);
    }

    public function test_8_gift_settlement_is_idempotent(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $e1 = $ledgerService->addCredits($creator, 'GIFT_EARNING', 60.0, 100, 40, 'Gift 1', 'gift_tx_99', 'gift');
        $e2 = $ledgerService->addCredits($creator, 'GIFT_EARNING', 60.0, 100, 40, 'Gift 1', 'gift_tx_99', 'gift');

        $this->assertEquals($e1->id, $e2->id);
        $this->assertEquals(1060, $creator->fresh()->wallet->credits);
    }

    public function test_9_paid_chat_credits_verified_creator(): void
    {
        $sender = $this->createNormalUser('male');
        $creator = $this->createVerifiedCreator('female');

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($sender, $creator);

        /** @var PaidMessagingService $paidMessaging */
        $paidMessaging = app(PaidMessagingService::class);

        $msg = $paidMessaging->sendPaidMessage($sender, $conversation, 'Hello paid message!');

        $this->assertTrue((bool) $msg->is_paid);
        $this->assertEquals(995, $sender->fresh()->wallet->coin_balance);
        $this->assertEquals(1003, $creator->fresh()->wallet->credits);
    }

    public function test_10_minimum_withdrawal_limit_enforced(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum withdrawal is 100 credits.');
        $withdrawalService->requestWithdrawal($creator, 50);
    }

    public function test_11_insufficient_credit_balance_prevents_withdrawal(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient creator credit balance');
        $withdrawalService->requestWithdrawal($creator, 50000);
    }

    public function test_12_active_pending_withdrawal_prevents_concurrent_withdrawal_request(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $withdrawal1 = $withdrawalService->requestWithdrawal($creator, 200);
        $this->assertContains($withdrawal1->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('active pending or processing withdrawal request');
        $withdrawalService->requestWithdrawal($creator, 200);
    }

    public function test_13_withdrawal_atomically_deducts_credits_and_preserves_conversion_rate(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $withdrawal = $withdrawalService->requestWithdrawal($creator, 300);

        $this->assertEquals(700, $creator->fresh()->wallet->credits);
        $this->assertEquals(300, $withdrawal->credits_deducted);
        $this->assertEquals(10.0, $withdrawal->conversion_rate);
        $this->assertEquals(30.0, $withdrawal->cash_amount_usd);
        $this->assertEquals(3900.0, $withdrawal->amount_kes);
    }

    public function test_14_failed_or_reversed_withdrawal_restores_creator_credits_via_reversal_ledger(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $withdrawal = $withdrawalService->requestWithdrawal($creator, 400);
        $this->assertEquals(600, $creator->fresh()->wallet->credits);

        $updated = $withdrawalService->processWithdrawal(
            $withdrawal,
            WithdrawalStatus::Failed,
            null,
            'Bank endpoint rejected transaction'
        );

        $this->assertEquals(WithdrawalStatus::Failed, $updated->status);
        $this->assertEquals(1000, $creator->fresh()->wallet->credits);

        $ledgerEntry = CreatorCreditLedger::where('user_id', $creator->id)
            ->where('transaction_type', 'WITHDRAWAL_REVERSAL')
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertEquals(400, $ledgerEntry->amount_credits);
    }

    public function test_15_mpesa_b2c_callback_is_idempotent(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $withdrawal = $withdrawalService->requestWithdrawal($creator, 200);

        /** @var MpesaService $mpesaService */
        $mpesaService = app(MpesaService::class);

        $payload = [
            'Result' => [
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'OriginatorConversationID' => (string) $withdrawal->id,
                'ConversationID' => 'AG_B2C_SUCCESS_99',
                'TransactionID' => 'QWE1234567',
            ],
        ];

        $res1 = $mpesaService->handleB2CResult($payload);
        $res2 = $mpesaService->handleB2CResult($payload);

        $this->assertEquals('success', $res1['status']);
        $this->assertEquals('already_processed', $res2['status']);
    }

    public function test_16_mpesa_b2c_timeout_callback_is_safe(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $withdrawal = $withdrawalService->requestWithdrawal($creator, 200);

        /** @var MpesaService $mpesaService */
        $mpesaService = app(MpesaService::class);

        $payload = [
            'Result' => [
                'OriginatorConversationID' => (string) $withdrawal->id,
                'ResultDesc' => 'Connection timeout to Safaricom endpoint',
            ],
        ];

        $res = $mpesaService->handleB2CTimeout($payload);

        $this->assertEquals('timeout_logged', $res['status']);
        $this->assertContains($withdrawal->fresh()->status, [WithdrawalStatus::Pending, WithdrawalStatus::Processing]);
    }

    public function test_17_credits_summary_endpoint_returns_real_backend_ledger_breakdown(): void
    {
        $creator = $this->createVerifiedCreator('female');

        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $ledgerService->creditCreator($creator, 60, 'call', 'c1', 'Call earning');
        $ledgerService->creditCreator($creator, 40, 'gift', 'g1', 'Gift earning');
        $ledgerService->creditCreator($creator, 20, 'chat', 'ch1', 'Chat earning');

        $response = $this->actingAs($creator->fresh(), 'sanctum')
            ->getJson('/api/v1/credits');

        $response->assertStatus(200)
            ->assertJsonPath('data.calls_credits', 60)
            ->assertJsonPath('data.gifts_credits', 40)
            ->assertJsonPath('data.chats_credits', 20)
            ->assertJsonPath('data.total_earned_credits', 120)
            ->assertJsonPath('data.available_credits', 1120);
    }

    public function test_18_creator_ledger_reconciliation_detects_and_fixes_discrepancies(): void
    {
        $creator = $this->createVerifiedCreator('female');
        $creator->wallet->update(['credits' => 0]);

        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $ledgerService->creditCreator($creator, 100, 'call', 'c_rec_1', 'Call');
        $ledgerService->creditCreator($creator, 50, 'gift', 'g_rec_1', 'Gift');

        // Simulate manual database drift on wallet table
        Wallet::where('user_id', $creator->id)->update(['credits' => 5000]);

        $result = $ledgerService->reconcileBalance($creator);

        $this->assertTrue($result['reconciled']);
        $this->assertEquals(150, $creator->fresh()->wallet->credits);
    }

    public function test_19_audited_manual_adjustment_creates_ledger_entry_and_adjusts_balance(): void
    {
        $creator = $this->createVerifiedCreator('female');
        $adminUser = User::factory()->create(['role' => UserRole::Admin]);

        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $entry = $ledgerService->addManualAdjustment($creator, 250.0, 'Compensation bonus', $adminUser);

        $this->assertEquals('MANUAL_CREDIT', $entry->transaction_type);
        $this->assertEquals(250, $entry->amount_credits);
        $this->assertEquals(1250, $creator->fresh()->wallet->credits);
        $this->assertStringContainsString('Compensation bonus', $entry->description);
    }

    public function test_20_payout_mpesa_phone_verification_required_prior_to_withdrawal(): void
    {
        $unverifiedPhoneCreator = $this->createVerifiedCreator('female', [
            'mpesa_phone_verified' => false,
            'mpesa_phone' => null,
        ]);

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('M-Pesa phone number must be verified');
        $withdrawalService->requestWithdrawal($unverifiedPhoneCreator, 200);
    }

    public function test_21_sensitive_mpesa_credentials_and_secrets_not_exposed_in_responses(): void
    {
        $creator = $this->createVerifiedCreator('female');

        $response = $this->actingAs($creator, 'sanctum')
            ->getJson('/api/v1/withdrawal-methods');

        $response->assertStatus(200);
        $json = $response->getContent();

        $this->assertStringNotContainsString('consumer_key', $json);
        $this->assertStringNotContainsString('consumer_secret', $json);
        $this->assertStringNotContainsString('passkey', $json);
        $this->assertStringNotContainsString('initiator_password', $json);
    }

    public function test_22_admin_configured_percentages_are_used_for_awarding_credits(): void
    {
        $femaleCreator = $this->createVerifiedCreator('female');

        /** @var MonetizationService $monetization */
        $monetization = app(MonetizationService::class);

        // Admin changes call share to 80% and chat share to 75%
        PlatformSetting::set('call_female_creator_share_pct', 80.0);
        PlatformSetting::set('chat_female_creator_share_pct', 75.0);
        PlatformSetting::set('gift_female_creator_share_pct', 85.0);

        $callSplit = $monetization->calculateSplit(100, 'call', 'female', $femaleCreator);
        $chatSplit = $monetization->calculateSplit(100, 'chat', 'female', $femaleCreator);
        $giftSplit = $monetization->calculateSplit(100, 'gift', 'female', $femaleCreator);

        $this->assertEquals(80, $callSplit['creator_amount']);
        $this->assertEquals(80.0, $callSplit['creator_share_pct']);

        $this->assertEquals(75, $chatSplit['creator_amount']);
        $this->assertEquals(75.0, $chatSplit['creator_share_pct']);

        $this->assertEquals(85, $giftSplit['creator_amount']);
        $this->assertEquals(85.0, $giftSplit['creator_share_pct']);
    }

    public function test_23_admin_configured_chat_coins_and_match_coins_are_dynamically_enforced(): void
    {
        $sender = $this->createNormalUser('male');
        $creator = $this->createVerifiedCreator('female');

        // Admin updates Chat Coins to 15 and Match Coins to 100
        PlatformSetting::set('message_cost', 15);
        PlatformSetting::set('chat_coins', 15);
        PlatformSetting::set('matching_token_cost', 100);
        PlatformSetting::set('match_coins', 100);

        /** @var PaidMessagingService $paidMessaging */
        $paidMessaging = app(PaidMessagingService::class);
        $conversation = app(ChatService::class)->getOrCreateConversation($sender, $creator);

        $msg = $paidMessaging->sendPaidMessage($sender, $conversation, 'Testing updated chat coins');

        $this->assertTrue((bool) $msg->is_paid);
        $this->assertEquals(985, $sender->fresh()->wallet->coin_balance); // 1000 - 15 = 985

        /** @var WalletService $walletService */
        $walletSummary = app(WalletService::class)->getWalletSummary($sender);

        $this->assertEquals(15, $walletSummary['rates']['chat_coins']);
        $this->assertEquals(100, $walletSummary['rates']['match_coins']);
    }
}
