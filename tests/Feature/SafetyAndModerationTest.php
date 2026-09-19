<?php

namespace Tests\Feature;

use App\Models\Gift;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SafetyAndModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_block_another_user_and_exclude_from_discovery(): void
    {
        $male = User::factory()->male()->create();
        $female1 = User::factory()->female()->create();
        $female2 = User::factory()->female()->create();

        UserProfile::factory()->create(['user_id' => $female1->id]);
        UserProfile::factory()->create(['user_id' => $female2->id]);

        // Block female1
        $response = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/blocks', [
                'blocked_id' => $female1->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('blocks', [
            'blocker_id' => $male->id,
            'blocked_id' => $female1->id,
        ]);

        // Discovery should not contain female1
        $discResponse = $this->actingAs($male, 'sanctum')
            ->getJson('/api/v1/discovery');

        $ids = collect($discResponse->json('data'))->pluck('id')->toArray();
        $this->assertNotContains($female1->id, $ids);
        $this->assertContains($female2->id, $ids);
    }

    public function test_user_can_unblock_blocked_user(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();

        $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/blocks', ['blocked_id' => $female->id]);

        $response = $this->actingAs($male, 'sanctum')
            ->deleteJson('/api/v1/blocks/'.$female->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('blocks', [
            'blocker_id' => $male->id,
            'blocked_id' => $female->id,
        ]);
    }

    public function test_user_can_report_another_user(): void
    {
        $reporter = User::factory()->create();
        $reported = User::factory()->create();

        $response = $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_id' => $reported->id,
                'category' => 'inappropriate_content',
                'reason' => 'Inappropriate photos',
                'description' => 'User uploaded inappropriate imagery.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $reporter->id,
            'reported_id' => $reported->id,
            'reason' => 'inappropriate_content',
            'status' => 'pending',
        ]);
    }

    public function test_audit_service_logs_admin_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $targetUser = User::factory()->create();

        /** @var AuditService $auditService */
        $auditService = app(AuditService::class);
        $action = $auditService->logAction(
            $admin,
            'suspend_user',
            User::class,
            (string) $targetUser->id,
            'Violation of terms of service'
        );

        $this->assertDatabaseHas('admin_actions', [
            'id' => $action->id,
            'admin_id' => $admin->id,
            'action' => 'suspend_user',
            'reason' => 'Violation of terms of service',
        ]);
    }

    public function test_self_block_returns_error_code(): void
    {
        $male = User::factory()->male()->create();

        $response = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/blocks', [
                'blocked_id' => $male->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SELF_BLOCK');
    }

    public function test_self_report_returns_error_code(): void
    {
        $male = User::factory()->male()->create();

        $response = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_id' => $male->id,
                'category' => 'harassment',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'SELF_REPORT');
    }

    public function test_report_rate_limiting_enforces_max_five_per_hour(): void
    {
        $reporter = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $reported = User::factory()->create();
            $this->actingAs($reporter, 'sanctum')
                ->postJson('/api/v1/reports', [
                    'reported_id' => $reported->id,
                    'category' => 'spam',
                ])
                ->assertStatus(201);
        }

        $sixthReported = User::factory()->create();
        $response = $this->actingAs($reporter, 'sanctum')
            ->postJson('/api/v1/reports', [
                'reported_id' => $sixthReported->id,
                'category' => 'spam',
            ]);

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'REPORT_RATE_LIMITED');
    }

    public function test_bidirectional_block_prevents_video_calls_and_gifts(): void
    {
        $male = User::factory()->male()->create();
        $female = User::factory()->female()->create();

        UserProfile::factory()->create(['user_id' => $male->id]);
        UserProfile::factory()->create(['user_id' => $female->id]);

        // Female blocks Male
        $this->actingAs($female, 'sanctum')
            ->postJson('/api/v1/blocks', ['blocked_id' => $male->id]);

        // Male attempts to call Female
        $callResponse = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $female->id,
                'call_type' => 'video',
            ]);

        $callResponse->assertStatus(422);

        // Male attempts to send gift to Female
        $gift = Gift::create(['name' => 'Rose', 'coin_price' => 50, 'is_active' => true]);
        Wallet::create(['user_id' => $male->id, 'coin_balance' => 500]);

        $giftResponse = $this->actingAs($male, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $female->id,
                'gift_id' => $gift->id,
            ]);

        $giftResponse->assertStatus(403);
    }
}
