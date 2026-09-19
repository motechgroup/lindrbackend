<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\ChatService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaidChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        PlatformSetting::set('message_cost', 5, 'Message cost in tokens');
        PlatformSetting::set('chat_female_creator_share_pct', 60.0, 'Female revenue share percentage');
    }

    public function test_male_user_with_coins_can_send_paid_message(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);

        /** @var WalletService $walletService */
        $walletService = app(WalletService::class);
        $walletService->creditCoins($male, 50);

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($male, $female);

        $response = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'Hello lovely!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_paid', true);

        // Assert male wallet debited 5 coins (balance 45)
        $this->assertEquals(45, $male->fresh()->wallet->coin_balance);

        // Assert female recipient credited with credits (5 * 0.60 = 3 credits)
        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $female->id,
            'amount_credits' => 3,
        ]);
    }

    public function test_male_user_without_coins_cannot_send_message(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create([
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($male, $female);

        $response = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'Hey without coins!',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('creator_credit_ledgers', 0);
    }

    public function test_female_user_sending_message_is_free(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($male, $female);

        $response = $this->actingAs($female, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversation->id.'/messages', [
                'content' => 'Hi handsome!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_paid', false);

        $this->assertDatabaseCount('creator_credit_ledgers', 0);
    }
}
