<?php

namespace App\Services;

use App\Models\LivenessVerification;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AdminVerificationService
{
    public function __construct(
        public AuditService $auditService,
        public NotificationService $notificationService
    ) {}

    /**
     * Verify that the performing user is an active administrator.
     */
    protected function authorizeAdmin(User $admin): void
    {
        if (! $admin->isAdmin() || ! $admin->isActive()) {
            throw new AuthorizationException('Unauthorized: Only active administrators can perform manual creator verification actions.');
        }
    }

    /**
     * Manually verify a user as an approved Lindr Creator.
     */
    public function verifyUser(User $admin, User $targetUser, ?string $reason = null): User
    {
        $this->authorizeAdmin($admin);

        $prevStatus = $targetUser->creator_status ?? 'unverified';

        $targetUser->update([
            'is_creator' => true,
            'creator_status' => 'approved',
            'liveness_verified_at' => now(),
        ]);

        // Update any pending liveness verifications for this user
        LivenessVerification::where('user_id', $targetUser->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'verified_at' => now(),
            ]);

        // Record audit log
        $this->auditService->logAction(
            $admin,
            'MANUAL_VERIFY',
            User::class,
            (string) $targetUser->id,
            $reason ?? 'Manually verified creator by admin',
            [
                'previous_status' => $prevStatus,
                'new_status' => 'approved',
                'admin_email' => $admin->email,
            ]
        );

        // Notify user
        $this->notificationService->notifyCreatorVerificationApproved($targetUser);

        return $targetUser->fresh();
    }

    /**
     * Manually reject a creator verification request.
     */
    public function rejectVerification(User $admin, User $targetUser, string $reason): User
    {
        $this->authorizeAdmin($admin);

        $prevStatus = $targetUser->creator_status ?? 'unverified';

        $targetUser->update([
            'is_creator' => false,
            'creator_status' => 'rejected',
        ]);

        LivenessVerification::where('user_id', $targetUser->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'failure_reason' => $reason,
                'verified_at' => now(),
            ]);

        $this->auditService->logAction(
            $admin,
            'MANUAL_REJECT',
            User::class,
            (string) $targetUser->id,
            $reason,
            [
                'previous_status' => $prevStatus,
                'new_status' => 'rejected',
                'admin_email' => $admin->email,
            ]
        );

        $this->notificationService->notifyCreatorVerificationRejected($targetUser, $reason);

        return $targetUser->fresh();
    }

    /**
     * Reset a user's verification status back to unverified.
     */
    public function resetVerification(User $admin, User $targetUser, string $reason): User
    {
        $this->authorizeAdmin($admin);

        $prevStatus = $targetUser->creator_status ?? 'unverified';

        $targetUser->update([
            'is_creator' => false,
            'creator_status' => 'unverified',
        ]);

        $this->auditService->logAction(
            $admin,
            'MANUAL_RESET',
            User::class,
            (string) $targetUser->id,
            $reason,
            [
                'previous_status' => $prevStatus,
                'new_status' => 'unverified',
                'admin_email' => $admin->email,
            ]
        );

        return $targetUser->fresh();
    }

    /**
     * Revoke creator verification for an already verified creator.
     */
    public function revokeVerification(User $admin, User $targetUser, string $reason): User
    {
        $this->authorizeAdmin($admin);

        $prevStatus = $targetUser->creator_status ?? 'approved';

        $targetUser->update([
            'is_creator' => false,
            'creator_status' => 'revoked',
        ]);

        // Historical ledger entries and transaction records remain untouched.

        $this->auditService->logAction(
            $admin,
            'MANUAL_REVOKE',
            User::class,
            (string) $targetUser->id,
            $reason,
            [
                'previous_status' => $prevStatus,
                'new_status' => 'revoked',
                'admin_email' => $admin->email,
            ]
        );

        $this->notificationService->notifyCreatorVerificationRevoked($targetUser, $reason);

        return $targetUser->fresh();
    }
}
