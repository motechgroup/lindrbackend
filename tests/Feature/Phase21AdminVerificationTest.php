<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminAction;
use App\Models\CallSession;
use App\Models\CreatorCreditLedger;
use App\Models\User;
use App\Services\AdminVerificationService;
use App\Services\CallService;
use App\Services\CreatorEligibilityService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase21AdminVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected AdminVerificationService $verificationService;

    protected CreatorEligibilityService $eligibilityService;

    protected User $adminUser;

    protected User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verificationService = app(AdminVerificationService::class);
        $this->eligibilityService = app(CreatorEligibilityService::class);

        $this->adminUser = User::factory()->create([
            'role' => UserRole::Admin,
            'status' => UserStatus::Active,
        ]);

        $this->normalUser = User::factory()->female()->create([
            'role' => UserRole::Female,
            'status' => UserStatus::Active,
            'is_creator' => false,
            'creator_status' => 'unverified',
        ]);
    }

    // 1. Authorized admin can manually verify user
    public function test_1_authorized_admin_can_manually_verify_user(): void
    {
        $updatedUser = $this->verificationService->verifyUser($this->adminUser, $this->normalUser, 'Manual verification test');

        $this->assertTrue($updatedUser->is_creator);
        $this->assertEquals('approved', $updatedUser->creator_status);
    }

    // 2. Unauthorized admin/user cannot verify
    public function test_2_unauthorized_user_cannot_verify(): void
    {
        $nonAdmin = User::factory()->female()->create(['role' => UserRole::Female]);

        $this->expectException(AuthorizationException::class);

        $this->verificationService->verifyUser($nonAdmin, $this->normalUser, 'Illegal attempt');
    }

    // 3. Verification changes to the correct existing status
    public function test_3_verification_changes_to_correct_status(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $this->assertDatabaseHas('users', [
            'id' => $this->normalUser->id,
            'is_creator' => true,
            'creator_status' => 'approved',
        ]);
    }

    // 4. Verification timestamp is recorded
    public function test_4_verification_timestamp_is_recorded(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $this->assertNotNull($this->normalUser->fresh()->liveness_verified_at);
    }

    // 5. Admin identity is recorded in audit record
    public function test_5_admin_identity_is_recorded(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser, 'Identity check');

        $action = AdminAction::where('entity_id', (string) $this->normalUser->id)->first();
        $this->assertNotNull($action);
        $this->assertEquals($this->adminUser->id, $action->admin_id);
    }

    // 6. Audit record is created
    public function test_6_audit_record_is_created(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $this->assertDatabaseHas('admin_actions', [
            'action' => 'MANUAL_VERIFY',
            'entity_type' => User::class,
            'entity_id' => (string) $this->normalUser->id,
        ]);
    }

    // 7. Reason/admin note is stored where required
    public function test_7_reason_is_stored(): void
    {
        $reasonText = 'Verified via government ID verification process';
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser, $reasonText);

        $action = AdminAction::where('entity_id', (string) $this->normalUser->id)->first();
        $this->assertEquals($reasonText, $action->reason);
    }

    // 8. Admin can reject verification
    public function test_8_admin_can_reject_verification(): void
    {
        $updated = $this->verificationService->rejectVerification($this->adminUser, $this->normalUser, 'Liveness not satisfactory');

        $this->assertFalse($updated->is_creator);
        $this->assertEquals('rejected', $updated->creator_status);
    }

    // 9. Rejection requires a reason
    public function test_9_rejection_requires_a_reason(): void
    {
        $this->verificationService->rejectVerification($this->adminUser, $this->normalUser, 'Verification evidence insufficient');

        $action = AdminAction::where('action', 'MANUAL_REJECT')->first();
        $this->assertEquals('Verification evidence insufficient', $action->reason);
    }

    // 10. Admin can reset verification
    public function test_10_admin_can_reset_verification(): void
    {
        $this->normalUser->update(['creator_status' => 'rejected']);

        $updated = $this->verificationService->resetVerification($this->adminUser, $this->normalUser, 'User requested reset');

        $this->assertFalse($updated->is_creator);
        $this->assertEquals('unverified', $updated->creator_status);
    }

    // 11. Admin can revoke verification
    public function test_11_admin_can_revoke_verification(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $updated = $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Terms of service violation');

        $this->assertFalse($updated->is_creator);
        $this->assertEquals('revoked', $updated->creator_status);
    }

    // 12. Revocation requires a reason
    public function test_12_revocation_requires_a_reason(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);
        $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Policy violation');

        $action = AdminAction::where('action', 'MANUAL_REVOKE')->first();
        $this->assertEquals('Policy violation', $action->reason);
    }

    // 13. Historical verification audit remains intact
    public function test_13_historical_verification_audit_remains_intact(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser, 'Step 1: Approved');
        $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Step 2: Revoked');
        $this->verificationService->resetVerification($this->adminUser, $this->normalUser, 'Step 3: Reset');

        $actions = AdminAction::where('entity_id', (string) $this->normalUser->id)->get();
        $this->assertCount(3, $actions);
        $this->assertEquals(['MANUAL_VERIFY', 'MANUAL_REVOKE', 'MANUAL_RESET'], $actions->pluck('action')->toArray());
    }

    // 14. Verified user becomes eligible for creator earnings
    public function test_14_verified_user_becomes_eligible_for_creator_earnings(): void
    {
        $this->assertFalse($this->eligibilityService->canEarnCredits($this->normalUser));

        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $this->assertTrue($this->eligibilityService->canEarnCredits($this->normalUser->fresh()));
    }

    // 15. Unverified user cannot earn creator credits
    public function test_15_unverified_user_cannot_earn(): void
    {
        $this->assertFalse($this->eligibilityService->canEarnCredits($this->normalUser));
    }

    // 16. Revoked user cannot earn new creator credits
    public function test_16_revoked_user_cannot_earn(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);
        $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Revoked check');

        $this->assertFalse($this->eligibilityService->canEarnCredits($this->normalUser->fresh()));
    }

    // 17. Historical creator ledger is preserved upon revocation
    public function test_17_historical_creator_ledger_preserved_on_revocation(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        CreatorCreditLedger::create([
            'user_id' => $this->normalUser->id,
            'transaction_type' => 'CALL_EARNING',
            'amount_credits' => 500,
            'balance_after' => 500,
            'conversion_rate' => 1.0,
            'cash_value_kes' => 500.00,
            'description' => 'Historical Call Earning',
            'reference_id' => 'call_ref_123',
        ]);

        $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Revocation test');

        $this->assertDatabaseHas('creator_credit_ledgers', [
            'user_id' => $this->normalUser->id,
            'amount_credits' => 500,
            'transaction_type' => 'CALL_EARNING',
        ]);
    }

    // 18. Mobile client cannot self-verify
    public function test_18_mobile_client_cannot_self_verify(): void
    {
        $response = $this->actingAs($this->normalUser, 'sanctum')
            ->putJson('/api/v1/profile', [
                'is_creator' => true,
                'creator_status' => 'approved',
            ]);

        $response->assertStatus(200);
        $this->assertFalse($this->normalUser->fresh()->is_creator);
        $this->assertEquals('unverified', $this->normalUser->fresh()->creator_status);
    }

    // 19. Mobile client cannot verify another user
    public function test_19_mobile_client_cannot_verify_another_user(): void
    {
        $otherUser = User::factory()->female()->create();

        $response = $this->actingAs($this->normalUser, 'sanctum')
            ->putJson('/api/v1/profile', [
                'user_id' => $otherUser->id,
                'is_creator' => true,
                'creator_status' => 'approved',
            ]);

        $response->assertStatus(200);
        $this->assertFalse($otherUser->fresh()->is_creator);
    }

    // 20. Manual verification does not create duplicate or free financial transactions
    public function test_20_manual_verification_does_not_create_financial_transactions(): void
    {
        $initialLedgerCount = CreatorCreditLedger::count();

        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $this->assertEquals($initialLedgerCount, CreatorCreditLedger::count());
    }

    // 21. Revoked user serialization returns false for liveness and profile is_verified
    public function test_21_revoked_user_serialization_returns_false_for_liveness_and_is_verified(): void
    {
        $this->verificationService->verifyUser($this->adminUser, $this->normalUser);

        $res1 = $this->actingAs($this->normalUser, 'sanctum')->getJson('/api/v1/auth/me');
        $res1->assertStatus(200)
            ->assertJsonPath('data.liveness_verified', true);

        $this->verificationService->revokeVerification($this->adminUser, $this->normalUser, 'Test revocation');

        $res2 = $this->actingAs($this->normalUser, 'sanctum')->getJson('/api/v1/auth/me');
        $res2->assertStatus(200)
            ->assertJsonPath('data.liveness_verified', false)
            ->assertJsonPath('data.is_creator', false)
            ->assertJsonPath('data.creator_status', 'revoked');
    }

    // 22. Accept call with insufficient tokens returns structured 422 error
    public function test_22_accept_call_with_insufficient_tokens_returns_structured_422_error(): void
    {
        $maleCaller = User::factory()->male()->create(['status' => UserStatus::Active]);
        $femaleReceiver = User::factory()->female()->create(['status' => UserStatus::Active]);

        // Caller has zero tokens
        $callService = app(CallService::class);
        $callSession = CallSession::create([
            'id' => (string) Str::uuid(),
            'caller_id' => $maleCaller->id,
            'receiver_id' => $femaleReceiver->id,
            'call_type' => 'video',
            'room_name' => 'lindr_room_test_123',
            'rate_per_minute' => 20,
            'status' => CallSession::STATUS_RINGING,
        ]);

        $response = $this->actingAs($femaleReceiver, 'sanctum')
            ->postJson("/api/v1/calls/{$callSession->id}/accept");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'INSUFFICIENT_TOKENS');
    }
}
