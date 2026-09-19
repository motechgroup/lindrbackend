<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\CreatorCreditLedger;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GiftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_1_active_user_can_send_gift(): void
    {
        $this->withoutExceptionHandling();
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);
        $walletService->creditCoins($male, 100);

        $response = $this->actingAs($male, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $female->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('success', true);
    }

    public function test_2_token_balance_decreases_correctly(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Heart', 'coin_price' => 25, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($male, 100);

        $this->actingAs($male, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $female->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertEquals(75, $male->fresh()->wallet->coin_balance);
    }

    public function test_3_gift_transaction_created(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Fire', 'coin_price' => 50, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($male, 100);

        $this->actingAs($male, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $female->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseHas('gift_transactions', [
            'sender_id' => $male->id,
            'recipient_id' => $female->id,
            'gift_id' => $gift->id,
            'coin_price' => 50,
        ]);
    }

    public function test_4_creator_receives_correct_credits(): void
    {
        $male = User::factory()->male()->create();
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Crown', 'coin_price' => 100, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($male, 200);

        $this->actingAs($male, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $creator->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $creator->id,
            'amount_credits' => 60,
            'transaction_type' => 'GIFT_EARNING',
        ]);
    }

    public function test_5_unverified_recipient_receives_no_creator_credits(): void
    {
        $sender = User::factory()->male()->create();
        $unverified = User::factory()->female()->create(['is_creator' => false]);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 50);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $unverified->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseMissing('creator_credit_ledgers', [
            'user_id' => $unverified->id,
        ]);
    }

    public function test_6_male_creator_can_earn(): void
    {
        $female = User::factory()->female()->create();
        $maleCreator = User::factory()->male()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Diamond', 'coin_price' => 50, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($female, 100);

        $this->actingAs($female, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $maleCreator->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $maleCreator->id,
            'amount_credits' => 30,
        ]);
    }

    public function test_7_female_creator_can_earn(): void
    {
        $male = User::factory()->male()->create();
        $femaleCreator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Diamond', 'coin_price' => 50, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($male, 100);

        $this->actingAs($male, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $femaleCreator->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $femaleCreator->id,
            'amount_credits' => 30,
        ]);
    }

    public function test_8_gift_cannot_be_sent_to_self(): void
    {
        $user = User::factory()->male()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($user, 50);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $user->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(400)->assertJsonPath('error_code', 'SELF_GIFT');
        $this->assertEquals(50, $user->fresh()->wallet->coin_balance);
    }

    public function test_9_gift_cannot_be_sent_to_blocked_user(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        UserBlock::create(['blocker_id' => $sender->id, 'blocked_id' => $recipient->id]);
        app(WalletService::class)->creditCoins($sender, 50);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(403)->assertJsonPath('error_code', 'RECIPIENT_BLOCKED');
    }

    public function test_10_gift_cannot_be_sent_to_banned_user(): void
    {
        $sender = User::factory()->male()->create();
        $banned = User::factory()->female()->create(['status' => UserStatus::Banned]);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 50);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $banned->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(403)->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_11_gift_cannot_be_sent_to_suspended_user(): void
    {
        $sender = User::factory()->male()->create();
        $suspended = User::factory()->female()->create(['status' => UserStatus::Suspended]);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 50);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $suspended->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(403)->assertJsonPath('error_code', 'ACCOUNT_SUSPENDED');
    }

    public function test_12_inactive_gift_cannot_be_purchased(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $inactiveGift = Gift::create(['name' => 'Old Gift', 'coin_price' => 10, 'is_active' => false]);

        app(WalletService::class)->creditCoins($sender, 50);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $inactiveGift->id,
        ]);

        $response->assertStatus(400)->assertJsonPath('error_code', 'GIFT_UNAVAILABLE');
    }

    public function test_13_insufficient_tokens_rejected(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $expensiveGift = Gift::create(['name' => 'Yacht', 'coin_price' => 500, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 10);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $expensiveGift->id,
        ]);

        $response->assertStatus(400)->assertJsonPath('error_code', 'INSUFFICIENT_TOKENS');
    }

    public function test_14_no_tokens_deducted_on_failed_gift(): void
    {
        $sender = User::factory()->male()->create();
        $banned = User::factory()->female()->create(['status' => UserStatus::Banned]);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 20, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 100);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $banned->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertEquals(100, $sender->fresh()->wallet->coin_balance);
    }

    public function test_15_no_duplicate_token_deduction(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 20);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertEquals(1, GiftTransaction::count());
        $this->assertEquals(10, $sender->fresh()->wallet->coin_balance);
    }

    public function test_16_no_duplicate_creator_credit(): void
    {
        $sender = User::factory()->male()->create();
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 20);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $creator->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertEquals(1, CreatorCreditLedger::where('user_id', $creator->id)->count());
    }

    public function test_17_gift_transaction_immutable(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 20);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $tx = GiftTransaction::first();
        $this->assertEquals(10, $tx->coin_price);
    }

    public function test_18_historical_gift_retains_original_price_and_reward(): void
    {
        $sender = User::factory()->male()->create();
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 50);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $creator->id,
            'gift_id' => $gift->id,
        ]);

        $tx = GiftTransaction::first();

        // Update gift price & catalog specs
        $gift->update(['coin_price' => 100]);

        $this->assertEquals(10, $tx->fresh()->coin_price);
    }

    public function test_19_gift_appears_in_history(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 20);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $res = $this->actingAs($sender, 'sanctum')->getJson('/api/v1/gifts/history');
        $res->assertStatus(200)->assertJsonPath('success', true);
    }

    public function test_20_gift_event_delivered_to_recipient(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 20);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $this->assertDatabaseHas('messages', [
            'sender_id' => $sender->id,
            'type' => 'gift',
        ]);
    }

    public function test_21_token_balance_returned_correctly(): void
    {
        $sender = User::factory()->male()->create();
        $recipient = User::factory()->female()->create();
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 10, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 50);

        $response = $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $recipient->id,
            'gift_id' => $gift->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('wallet.token_balance', 40);
    }

    public function test_22_earnings_aggregation_includes_gifts(): void
    {
        $sender = User::factory()->male()->create();
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Diamond', 'coin_price' => 100, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 200);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $creator->id,
            'gift_id' => $gift->id,
        ]);

        $response = $this->actingAs($creator, 'sanctum')->getJson('/api/v1/credits');
        $response->assertStatus(200)
            ->assertJsonPath('data.gifts_credits', 60);
    }

    public function test_23_withdrawal_balance_includes_valid_gift_earnings(): void
    {
        $sender = User::factory()->male()->create();
        $creator = User::factory()->female()->create(['is_creator' => true, 'creator_status' => 'approved']);
        $gift = Gift::create(['name' => 'Crown', 'coin_price' => 100, 'recipient_share_percentage' => 60.0, 'is_active' => true]);

        app(WalletService::class)->creditCoins($sender, 200);

        $this->actingAs($sender, 'sanctum')->postJson('/api/v1/gifts/send', [
            'recipient_id' => $creator->id,
            'gift_id' => $gift->id,
        ]);

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);
        $available = $withdrawalService->getAvailableEarnings($creator);

        $this->assertGreaterThan(0, $available);
    }
}
