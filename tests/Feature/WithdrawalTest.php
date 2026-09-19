<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\CreditLedgerService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::set('minimum_withdrawal_credits', 100);
        PlatformSetting::set('credits_per_usd', 10.0);
    }

    protected function createVerifiedCreator(array $attributes = [], string $gender = 'female'): User
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
                'gender' => $gender,
            ]
        );

        /** @var CreditLedgerService $creditService */
        $creditService = app(CreditLedgerService::class);
        $creditService->addCredits($user, 'CALL_EARNING', 1000, 1000, 0, 'Test initial credits');

        return $user->fresh(['profile']);
    }

    public function test_male_and_female_verified_creators_can_withdraw(): void
    {
        $female = $this->createVerifiedCreator([], 'female');
        $male = $this->createVerifiedCreator([], 'male');

        // Female request
        $resFemale = $this->actingAs($female, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 200]);
        $resFemale->assertStatus(201)->assertJsonPath('data.status', 'processing');

        // Male request
        $resMale = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 300]);
        $resMale->assertStatus(201)->assertJsonPath('data.status', 'processing');
    }

    public function test_unverified_regular_user_cannot_withdraw(): void
    {
        $user = User::factory()->create(['is_creator' => false, 'creator_status' => 'none', 'status' => UserStatus::Active]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 200]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_suspended_or_banned_user_cannot_withdraw(): void
    {
        $suspended = $this->createVerifiedCreator(['status' => UserStatus::Suspended]);

        $response = $this->actingAs($suspended, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 200]);

        $response->assertStatus(403);
    }

    public function test_unverified_mpesa_phone_cannot_withdraw(): void
    {
        $user = $this->createVerifiedCreator(['mpesa_phone_verified' => false]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', ['credits' => 200]);

        $response->assertStatus(400);
    }

    public function test_idempotency_key_prevents_duplicate_withdrawal_requests(): void
    {
        $creator = $this->createVerifiedCreator();

        $key = 'idemp_key_12345';
        $res1 = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', [
                'credits' => 200,
                'idempotency_key' => $key,
            ]);

        $res1->assertStatus(201);
        $wId1 = $res1->json('data.id');

        // Duplicate call with same idempotency key
        $res2 = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/v1/withdrawals/request', [
                'credits' => 200,
                'idempotency_key' => $key,
            ]);

        $res2->assertStatus(201);
        $wId2 = $res2->json('data.id');

        $this->assertEquals($wId1, $wId2);
        // Balance should have been deducted only ONCE (1000 - 200 = 800)
        $this->assertEquals(800, Wallet::where('user_id', $creator->id)->value('credits'));
    }

    public function test_failed_withdrawal_creates_reversal_ledger_and_restores_credits(): void
    {
        $creator = $this->createVerifiedCreator();
        $admin = User::factory()->admin()->create();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 400, '254712345678');

        $this->assertEquals(600, Wallet::where('user_id', $creator->id)->value('credits'));

        // Admin marks failed
        $withdrawalService->processWithdrawal(
            $withdrawal,
            WithdrawalStatus::Failed,
            $admin,
            'M-Pesa API rejected B2C payload',
            null,
            'Insufficient float balance'
        );

        // Credits restored (600 + 400 = 1000)
        $this->assertEquals(1000, Wallet::where('user_id', $creator->id)->value('credits'));

        // Auditable WITHDRAWAL_REVERSAL record created
        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $creator->id,
            'transaction_type' => 'WITHDRAWAL_REVERSAL',
            'amount_credits' => 400,
            'reference_id' => (string) $withdrawal->id,
        ]);
    }

    public function test_reversed_withdrawal_payout_restores_credits_once(): void
    {
        $creator = $this->createVerifiedCreator();
        $admin = User::factory()->admin()->create();

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 500);

        // Transition: PENDING -> SUCCESS -> REVERSED
        $withdrawalService->processWithdrawal($withdrawal, WithdrawalStatus::Success, $admin, 'Success', 'MPESA_REF_112233');
        $this->assertEquals(500, Wallet::where('user_id', $creator->id)->value('credits'));

        $withdrawalService->processWithdrawal($withdrawal, WithdrawalStatus::Reversed, $admin, 'Reversal per M-Pesa error');
        $this->assertEquals(1000, Wallet::where('user_id', $creator->id)->value('credits'));
    }

    public function test_historical_conversion_rate_is_preserved(): void
    {
        PlatformSetting::set('credits_per_usd', 10.0);

        $creator = $this->createVerifiedCreator();
        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 500);

        $this->assertEquals(10.0, (float) $withdrawal->conversion_rate);

        // Change global conversion rate afterwards
        PlatformSetting::set('credits_per_usd', 20.0);

        // Original withdrawal retains 10.0
        $this->assertEquals(10.0, (float) $withdrawal->fresh()->conversion_rate);
    }

    public function test_ledger_reconciliation_verifies_balances(): void
    {
        $creator = $this->createVerifiedCreator();
        /** @var CreditLedgerService $ledgerService */
        $ledgerService = app(CreditLedgerService::class);

        $ledgerService->addCredits($creator, 'GIFT_EARNING', 300, 300, 0, 'Gift');

        $reconcile = $ledgerService->reconcileBalance($creator);
        $this->assertEquals(1300, $reconcile['ledger_sum']);
        $this->assertEquals(1300, $reconcile['wallet_balance']);
        $this->assertFalse($reconcile['reconciled']);
    }

    public function test_invalid_status_transition_throws_domain_exception(): void
    {
        $creator = $this->createVerifiedCreator();
        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->requestWithdrawal($creator, 200);

        // Fail withdrawal
        $withdrawalService->processWithdrawal($withdrawal, WithdrawalStatus::Failed);

        // Cannot move from FAILED to PENDING
        $this->expectException(\DomainException::class);
        $withdrawalService->processWithdrawal($withdrawal, WithdrawalStatus::Pending);
    }
}
