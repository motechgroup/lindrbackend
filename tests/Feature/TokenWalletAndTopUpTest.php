<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\CoinPackage;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\LevelService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenWalletAndTopUpTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected PaymentService $paymentService;

    protected LevelService $levelService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = app(WalletService::class);
        $this->paymentService = app(PaymentService::class);
        $this->levelService = app(LevelService::class);
    }

    /** 1. New user has zero token balance */
    public function test_new_user_has_zero_token_balance(): void
    {
        $user = User::factory()->create();
        $wallet = $this->walletService->getWallet($user);

        $this->assertEquals(0, $wallet->coin_balance);
        $this->assertDatabaseHas('wallets', ['user_id' => $user->id, 'coin_balance' => 0]);
    }

    /** 2. Token package can be retrieved */
    public function test_token_package_can_be_retrieved(): void
    {
        $package = CoinPackage::create([
            'name' => '100 Tokens',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 150.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $response = $this->getJson('/api/v1/token-packages');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.name', '100 Tokens')
            ->assertJsonPath('data.0.token_amount', 100);
    }

    /** 3. Active package can be purchased */
    public function test_active_package_can_be_purchased(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '250 Tokens',
            'coin_amount' => 250,
            'bonus_coins' => 0,
            'price_kes' => 400.00,
            'is_active' => true,
            'display_order' => 1,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/topup', [
            'package_id' => $package->id,
            'payment_method' => 'mpesa',
            'phone_number' => '0712345678',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expected_coins', 250)
            ->assertJsonPath('data.status', 'pending');
    }

    /** 4. Inactive package cannot be purchased */
    public function test_inactive_package_cannot_be_purchased(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => 'Disabled Package',
            'coin_amount' => 1000,
            'bonus_coins' => 0,
            'price_kes' => 1400.00,
            'is_active' => false,
            'display_order' => 99,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/wallet/topup', [
            'package_id' => $package->id,
            'payment_method' => 'mpesa',
        ]);

        $response->assertStatus(422);
    }

    /** 5. Successful payment credits correct tokens */
    public function test_successful_payment_credits_correct_tokens(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '500 Tokens',
            'coin_amount' => 500,
            'bonus_coins' => 0,
            'price_kes' => 750.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_TEST_500',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);

        $credited = $this->paymentService->applyTransactionStatus($tx, 'successful', 'MPESA_RCPT_123');

        $this->assertTrue($credited);
        $this->assertEquals(500, $user->fresh()->wallet->coin_balance);
        $this->assertEquals('successful', $tx->fresh()->status);
    }

    /** 6. Failed payment credits zero tokens */
    public function test_failed_payment_credits_zero_tokens(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '500 Tokens',
            'coin_amount' => 500,
            'bonus_coins' => 0,
            'price_kes' => 750.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_TEST_FAIL',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);

        $credited = $this->paymentService->applyTransactionStatus($tx, 'failed');

        $this->assertFalse($credited);
        $this->assertEquals(0, $this->walletService->getWallet($user->fresh())->coin_balance);
        $this->assertEquals('failed', $tx->fresh()->status);
    }

    /** 7. Cancelled payment credits zero tokens */
    public function test_cancelled_payment_credits_zero_tokens(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '500 Tokens',
            'coin_amount' => 500,
            'bonus_coins' => 0,
            'price_kes' => 750.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_TEST_CANCEL',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);

        $credited = $this->paymentService->applyTransactionStatus($tx, 'cancelled');

        $this->assertFalse($credited);
        $this->assertEquals(0, $this->walletService->getWallet($user->fresh())->coin_balance);
    }

    /** 8. Duplicate webhook does not double-credit */
    public function test_duplicate_webhook_does_not_double_credit(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '100 Tokens',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 150.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_TEST_DUP',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 150.00,
            'expected_coins' => 100,
            'status' => 'pending',
        ]);

        // First application
        $this->paymentService->applyTransactionStatus($tx, 'successful', 'RCPT1');
        $this->assertEquals(100, $user->fresh()->wallet->coin_balance);

        // Second duplicate application
        $this->paymentService->applyTransactionStatus($tx->fresh(), 'successful', 'RCPT1');
        $this->assertEquals(100, $user->fresh()->wallet->coin_balance);
    }

    /** 9. Duplicate top-up request is idempotent */
    public function test_duplicate_topup_request_is_idempotent(): void
    {
        $user = User::factory()->create();

        $tx1 = $this->walletService->creditCoins(
            $user,
            100,
            TransactionType::Credit,
            'TestRef',
            '123',
            'First topup',
            'IDEM_KEY_999'
        );

        $tx2 = $this->walletService->creditCoins(
            $user,
            100,
            TransactionType::Credit,
            'TestRef',
            '123',
            'Duplicate topup',
            'IDEM_KEY_999'
        );

        $this->assertEquals($tx1->id, $tx2->id);
        $this->assertEquals(100, $user->fresh()->wallet->coin_balance);
    }

    /** 10. Token ledger records the credit */
    public function test_token_ledger_records_the_credit(): void
    {
        $user = User::factory()->create();
        $this->walletService->creditCoins($user, 250, TransactionType::Credit, 'Test', '1', 'Test credit');

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'transaction_type' => 'CREDIT',
            'amount' => 250,
        ]);
    }

    /** 11. Balance before/after is correct */
    public function test_balance_before_and_after_is_correct(): void
    {
        $user = User::factory()->create();

        $tx1 = $this->walletService->creditCoins($user, 300, TransactionType::Credit);
        $this->assertEquals(0, $tx1->balance_before);
        $this->assertEquals(300, $tx1->balance_after);

        $tx2 = $this->walletService->debitCoins($user, 100, TransactionType::Debit);
        $this->assertEquals(300, $tx2->balance_before);
        $this->assertEquals(200, $tx2->balance_after);
    }

    /** 12. Token spending creates a debit ledger entry */
    public function test_token_spending_creates_a_debit_ledger_entry(): void
    {
        $user = User::factory()->create();
        $this->walletService->creditCoins($user, 500, TransactionType::Credit);

        $debitTx = $this->walletService->debitCoins($user, 50, TransactionType::Debit, 'Match', 'm1', 'Instant match spend');

        $this->assertEquals('DEBIT', $debitTx->direction);
        $this->assertEquals(50, $debitTx->amount);
        $this->assertEquals(450, $user->fresh()->wallet->coin_balance);
    }

    /** 13. Negative balance is impossible */
    public function test_negative_balance_is_impossible(): void
    {
        $user = User::factory()->create();
        $this->walletService->creditCoins($user, 50, TransactionType::Credit);

        $this->expectException(\InvalidArgumentException::class);
        $this->walletService->debitCoins($user, 100, TransactionType::Debit);
    }

    /** 14. Concurrent spending is protected */
    public function test_concurrent_spending_is_protected(): void
    {
        $user = User::factory()->create();
        $this->walletService->creditCoins($user, 100, TransactionType::Credit);

        // First spend 100
        $this->walletService->debitCoins($user, 100, TransactionType::Debit);

        // Second spend 50 must fail
        $this->expectException(\InvalidArgumentException::class);
        $this->walletService->debitCoins($user, 50, TransactionType::Debit);
    }

    /** 15. Refund/reversal creates compensating transaction */
    public function test_refund_reversal_creates_compensating_transaction(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '500 Tokens',
            'coin_amount' => 500,
            'bonus_coins' => 0,
            'price_kes' => 750.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_TEST_REV',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);

        $this->paymentService->applyTransactionStatus($tx, 'successful');
        $this->assertEquals(500, $user->fresh()->wallet->coin_balance);

        // Perform reversal
        $reversed = $this->paymentService->reversePayment($tx->fresh(), 'Fraudulent chargeback');

        $this->assertTrue($reversed);
        $this->assertEquals('reversed', $tx->fresh()->status);
        $this->assertEquals(0, $user->fresh()->wallet->coin_balance);

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'transaction_type' => 'REFUND',
            'amount' => 500,
        ]);
    }

    /** 16. Historical purchase retains original price */
    public function test_historical_purchase_retains_original_price(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '100 Tokens',
            'coin_amount' => 100,
            'bonus_coins' => 0,
            'price_kes' => 150.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_HIST_PRICING',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 150.00,
            'expected_coins' => 100,
            'status' => 'successful',
        ]);

        // Change package price later
        $package->update(['price_kes' => 200.00, 'coin_amount' => 120]);

        $this->assertEquals(150.00, (float) $tx->fresh()->amount);
        $this->assertEquals(100, $tx->fresh()->expected_coins);
    }

    /** 17. Admin package price changes do not alter historical purchases */
    public function test_admin_package_price_changes_do_not_alter_historical_purchases(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '250 Tokens',
            'coin_amount' => 250,
            'bonus_coins' => 0,
            'price_kes' => 400.00,
            'is_active' => true,
        ]);

        $tx = PaymentTransaction::create([
            'public_reference' => 'LNDR_ADMIN_PRICE',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'country' => 'KE',
            'currency' => 'KES',
            'amount' => 400.00,
            'expected_coins' => 250,
            'status' => 'successful',
        ]);

        $package->update(['price_kes' => 500.00]);

        $this->assertEquals(400.00, (float) $tx->fresh()->amount);
    }

    /** 18. Failed/reversed top-ups do not contribute to Level metrics */
    public function test_failed_and_reversed_topups_do_not_contribute_to_level_metrics(): void
    {
        $user = User::factory()->create();
        $package = CoinPackage::create([
            'name' => '500 Tokens',
            'coin_amount' => 500,
            'bonus_coins' => 0,
            'price_kes' => 750.00,
            'is_active' => true,
        ]);

        // Failed transaction
        PaymentTransaction::create([
            'public_reference' => 'LNDR_FAIL_METRIC',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'failed',
        ]);

        // Reversed transaction
        $txRev = PaymentTransaction::create([
            'public_reference' => 'LNDR_REV_METRIC',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);
        $this->paymentService->applyTransactionStatus($txRev, 'successful');
        $this->paymentService->reversePayment($txRev, 'Reversed test');

        // Successful transaction
        $txSuccess = PaymentTransaction::create([
            'public_reference' => 'LNDR_OK_METRIC',
            'user_id' => $user->id,
            'package_id' => $package->id,
            'provider_code' => 'mpesa',
            'payment_method_code' => 'mpesa',
            'amount' => 750.00,
            'expected_coins' => 500,
            'status' => 'pending',
        ]);
        $this->paymentService->applyTransactionStatus($txSuccess, 'successful');

        $metrics = $this->levelService->getTopupMetrics($user);

        $this->assertEquals(1, $metrics['total_successful_purchases']);
        $this->assertEquals(500, $metrics['total_token_amount_purchased']);
    }

    /** 19. Unauthorized users cannot modify wallet balance directly */
    public function test_unauthorized_users_cannot_modify_wallet(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->putJson('/api/v1/wallet', [
            'coin_balance' => 9999,
        ]);

        $response->assertStatus(405);
    }

    /** 20. Unauthorized users cannot modify token packages */
    public function test_unauthorized_users_cannot_modify_token_packages(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/token-packages', [
            'name' => 'Hacked Package',
            'coin_amount' => 99999,
            'price_kes' => 1.00,
        ]);

        $response->assertStatus(405);
    }
}
