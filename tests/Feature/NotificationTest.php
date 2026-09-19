<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_receives_and_fetches_notifications(): void
    {
        $user = User::factory()->create();

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);
        $notificationService->notifyUser(
            $user,
            'INCOMING_DIRECT_CALL',
            'Incoming Video Call',
            'Sarah is calling you',
            ['call_id' => '123']
        );

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Incoming Video Call');

        $notificationId = $response->json('data.0.id');

        // Mark as read
        $readResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/'.$notificationId.'/read');

        $readResponse->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_push_token_registration_and_deletion(): void
    {
        $user = User::factory()->create();

        // Register push token
        $regResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices/push-token', [
                'push_token' => 'ExponentPushToken[12345]',
                'platform' => 'ios',
                'device_name' => 'iPhone 15 Pro',
            ]);

        $regResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('user_devices', [
            'user_id' => $user->id,
            'push_token' => 'ExponentPushToken[12345]',
        ]);

        // Unregister push token
        $delResponse = $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/v1/devices/push-token', [
                'push_token' => 'ExponentPushToken[12345]',
            ]);

        $delResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_devices', [
            'user_id' => $user->id,
            'push_token' => 'ExponentPushToken[12345]',
        ]);
    }

    public function test_notification_preferences_filtering(): void
    {
        $user = User::factory()->create();

        // Disable gifts category in preferences
        $prefResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/settings', [
                'categories' => [
                    'gifts' => false,
                    'calls' => true,
                ],
            ]);

        $prefResponse->assertStatus(200)
            ->assertJsonPath('data.categories.gifts', false);

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);

        // Try sending gift notification -> should be suppressed
        $notificationService->notifyUser($user, 'GIFT_RECEIVED', 'New Gift', 'You received a Rose');

        // Try sending call notification -> should be delivered
        $notificationService->notifyUser($user, 'INCOMING_DIRECT_CALL', 'Incoming Call', 'Call in progress');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200);
        $notifications = $response->json('data');

        $this->assertCount(1, $notifications);
        $this->assertEquals('INCOMING_DIRECT_CALL', $notifications[0]['type']);
    }

    public function test_unread_count_and_mark_all_as_read(): void
    {
        $user = User::factory()->create();

        /** @var NotificationService $notificationService */
        $notificationService = app(NotificationService::class);
        $notificationService->notifyUser($user, 'NEW_MESSAGE', 'Message 1', 'Hello 1');
        $notificationService->notifyUser($user, 'NEW_MESSAGE', 'Message 2', 'Hello 2');

        $countResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $countResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);

        // Mark all as read
        $readAllResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/notifications/read-all');

        $readAllResponse->assertStatus(200)
            ->assertJsonPath('data.updated_count', 2);

        $countResponseAfter = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/notifications/unread-count');

        $countResponseAfter->assertStatus(200)
            ->assertJsonPath('data.unread_count', 0);
    }

    public function test_presence_heartbeat_and_status_transitions(): void
    {
        $user = User::factory()->create();

        // Send heartbeat 'online'
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/presence/heartbeat', [
                'status' => 'online',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'online');

        /** @var PresenceService $presenceService */
        $presenceService = app(PresenceService::class);
        $presence = $presenceService->getUserPresence($user->fresh());
        $this->assertEquals('online', $presence['status']);
    }
}
