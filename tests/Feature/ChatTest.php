<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_start_conversation_and_send_message(): void
    {
        $userA = User::factory()->female()->create();
        $userB = User::factory()->female()->create(); // female users exchange free text in basic test

        $response = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/conversations', [
                'recipient_id' => $userB->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $conversationId = $response->json('data.id');

        $msgResponse = $this->actingAs($userA, 'sanctum')
            ->postJson('/api/v1/conversations/'.$conversationId.'/messages', [
                'content' => 'Hello there!',
            ]);

        $msgResponse->assertStatus(201)
            ->assertJsonPath('data.content', 'Hello there!');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $userA->id,
            'recipient_id' => $userB->id,
            'content' => 'Hello there!',
        ]);
    }

    public function test_user_can_view_conversation_list_and_messages(): void
    {
        $userA = User::factory()->female()->create();
        $userB = User::factory()->female()->create();

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($userA, $userB);
        $chatService->sendMessage($userA, $conversation, 'Testing msg 1');
        $chatService->sendMessage($userB, $conversation, 'Testing msg 2');

        // Fetch conversations
        $convResponse = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/conversations');

        $convResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Fetch messages
        $msgResponse = $this->actingAs($userA, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation->id.'/messages');

        $msgResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');
    }

    public function test_non_participant_cannot_access_messages(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $userC = User::factory()->create();

        /** @var ChatService $chatService */
        $chatService = app(ChatService::class);
        $conversation = $chatService->getOrCreateConversation($userA, $userB);

        $response = $this->actingAs($userC, 'sanctum')
            ->getJson('/api/v1/conversations/'.$conversation->id.'/messages');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
