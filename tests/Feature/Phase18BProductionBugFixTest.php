<?php

namespace Tests\Feature;

use App\Models\CallSession;
use App\Models\CreatorCreditLedger;
use App\Models\Gift;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\Wallet;
use App\Services\CallService;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase18BProductionBugFixTest extends TestCase
{
    use RefreshDatabase;

    protected User $caller;

    protected User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed platform settings
        $this->artisan('db:seed');

        $this->caller = User::factory()->create([
            'role' => 'male',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        Wallet::create(['user_id' => $this->caller->id, 'coin_balance' => 500]);
        UserProfile::create(['user_id' => $this->caller->id, 'display_name' => 'Caller', 'online_status' => 'available']);

        $this->creator = User::factory()->create([
            'role' => 'female',
            'status' => 'active',
            'is_creator' => true,
            'creator_status' => 'approved',
            'email_verified_at' => now(),
        ]);
        Wallet::create(['user_id' => $this->creator->id, 'coin_balance' => 0]);
        UserProfile::create(['user_id' => $this->creator->id, 'display_name' => 'Creator', 'online_status' => 'available']);
    }

    public function test_call_request_returns_id_property_and_navigates_caller(): void
    {
        $response = $this->actingAs($this->caller, 'sanctum')
            ->postJson('/api/v1/calls/request', [
                'receiver_id' => $this->creator->id,
                'call_type' => 'video',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['id', 'call_session_id', 'room_name', 'status'],
            ]);

        $callId = $response->json('data.id');
        $this->assertNotEmpty($callId);
        $this->assertEquals($callId, $response->json('data.call_session_id'));
    }

    public function test_caller_cancel_resets_presence_and_ends_call(): void
    {
        $requestRes = app(CallService::class)->requestCall($this->caller, $this->creator, 'video');
        $callSession = CallSession::findOrFail($requestRes['id']);

        $response = $this->actingAs($this->caller, 'sanctum')
            ->postJson("/api/v1/calls/{$callSession->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $callSession->refresh();
        $this->assertEquals('CANCELLED', $callSession->status);

        $this->assertEquals('available', $this->caller->fresh()->profile->online_status);
        $this->assertEquals('available', $this->creator->fresh()->profile->online_status);
    }

    public function test_call_accept_and_settlement_creates_creator_call_earning_ledger_entry(): void
    {
        $requestRes = app(CallService::class)->requestCall($this->caller, $this->creator, 'video');
        $callSession = CallSession::findOrFail($requestRes['id']);

        $acceptRes = $this->actingAs($this->creator, 'sanctum')
            ->postJson("/api/v1/calls/{$callSession->id}/accept");

        $acceptRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $callSession->refresh();
        $this->assertEquals('CONNECTED', $callSession->status);

        // Verify credit ledger has a CALL_EARNING transaction for creator
        $ledgerEntry = CreatorCreditLedger::where('user_id', $this->creator->id)
            ->whereIn('transaction_type', ['CALL_EARNING', 'call'])
            ->first();

        $this->assertNotNull($ledgerEntry);
        $this->assertGreaterThan(0, $ledgerEntry->amount_credits);
    }

    public function test_presence_self_healing_resets_stale_busy_status_when_no_active_call_exists(): void
    {
        // Set creator profile to busy manually (simulating orphan/stale session)
        $this->creator->profile->update(['online_status' => 'busy']);

        $presence = app(PresenceService::class)->getUserPresence($this->creator);

        $this->assertEquals('online', $presence['status']);
        $this->assertTrue($presence['is_available']);
        $this->assertEquals('available', $this->creator->fresh()->profile->online_status);
    }

    public function test_profile_update_validates_username_uniqueness_and_updates_name(): void
    {
        $response = $this->actingAs($this->caller, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'NewUniqueUsername',
                'bio' => 'Updated bio text for testing.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals('NewUniqueUsername', $this->caller->fresh()->name);
        $this->assertEquals('Updated bio text for testing.', $this->caller->fresh()->profile->bio);

        // Duplicate username should fail validation
        $dupRes = $this->actingAs($this->creator, 'sanctum')
            ->putJson('/api/v1/profile', [
                'name' => 'NewUniqueUsername',
            ]);

        $dupRes->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_primary_photo_upload_updates_user_avatar_url(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $response = $this->actingAs($this->caller, 'sanctum')
            ->postJson('/api/v1/photos', [
                'photo' => $file,
                'is_primary' => true,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertNotNull($this->caller->fresh()->avatar);
        $this->assertStringContainsString('photos/', $this->caller->fresh()->avatar);
    }

    public function test_gift_send_succeeds_under_gift_rate_limiter(): void
    {
        $gift = Gift::firstOrCreate(
            ['name' => 'Test Rose'],
            ['coin_price' => 10, 'is_active' => true]
        );

        $response = $this->actingAs($this->caller, 'sanctum')
            ->postJson('/api/v1/gifts/send', [
                'recipient_id' => $this->creator->id,
                'gift_id' => $gift->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);
    }
}
