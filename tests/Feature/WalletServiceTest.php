<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_credit_and_debit_coins_atomically(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);

        // Credit 100 coins
        $txCredit = $walletService->creditCoins(
            $user,
            100,
            TransactionType::Credit,
            'test',
            '1',
            'Test deposit'
        );

        $this->assertEquals(100, $txCredit->balance_after);
        $this->assertEquals(100, $user->fresh()->wallet->coin_balance);

        // Debit 30 coins
        $txDebit = $walletService->debitCoins(
            $user,
            30,
            TransactionType::Debit,
            'test',
            '2',
            'Test purchase'
        );

        $this->assertEquals(70, $txDebit->balance_after);
        $this->assertEquals(70, $user->fresh()->wallet->coin_balance);
    }

    public function test_debiting_more_than_balance_throws_exception(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);
        $walletService->creditCoins($user, 20);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient coin balance.');

        $walletService->debitCoins($user, 50);
    }

    public function test_duplicate_idempotency_key_prevents_double_credit(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);

        $idempotencyKey = 'tx_unique_123456';

        $tx1 = $walletService->creditCoins($user, 50, TransactionType::Credit, null, null, 'First', $idempotencyKey);
        $tx2 = $walletService->creditCoins($user, 50, TransactionType::Credit, null, null, 'Second duplicate', $idempotencyKey);

        $this->assertEquals($tx1->id, $tx2->id);
        $this->assertEquals(50, $user->fresh()->wallet->coin_balance);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_user_can_fetch_wallet_balance_and_transactions_via_api(): void
    {
        $user = User::factory()->create();
        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);
        $walletService->creditCoins($user, 200);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/wallet');

        $response->assertStatus(200)
            ->assertJsonPath('data.coin_balance', 200);

        $txResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/wallet/transactions');

        $txResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }
}
