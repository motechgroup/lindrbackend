<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\LevelPenalty;
use App\Models\ModerationAction;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModerationService
{
    public function __construct(
        public LevelService $levelService,
        public SpotlightService $spotlightService,
        public CommunityGuidelinesService $guidelinesService,
        public ?NotificationService $notificationService = null
    ) {}

    /**
     * Submit a user report with structured category, rate limiting, and duplicate protection.
     *
     * @return array{success: bool, message: string, report: UserReport}
     */
    public function submitReport(User $reporter, int $reportedId, string $category, ?string $description = null): array
    {
        if ($reporter->id === $reportedId) {
            throw new \InvalidArgumentException('You cannot report yourself.');
        }

        $reportedUser = User::findOrFail($reportedId);

        // 1. Rate-limiting check: max 5 reports per hour per user
        $recentReportsCount = UserReport::where('reporter_id', $reporter->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentReportsCount >= 5) {
            throw new \DomainException('Report limit reached. Please wait before submitting additional reports.');
        }

        // 2. Duplicate protection check: same reporter, reported user, category within 24h
        $existingDuplicate = UserReport::where('reporter_id', $reporter->id)
            ->where('reported_id', $reportedId)
            ->where('category', $category)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        if ($existingDuplicate) {
            return [
                'success' => true,
                'message' => 'Thanks. Your report has been received and will be reviewed.',
                'report' => $existingDuplicate,
            ];
        }

        // 3. Attach current published guidelines version
        $latestGuidelines = $this->guidelinesService->getLatestPublished();

        $report = UserReport::create([
            'reporter_id' => $reporter->id,
            'reported_id' => $reportedId,
            'category' => $category,
            'reason' => $category,
            'description' => $description,
            'status' => 'pending',
            'priority' => 'normal',
            'guidelines_version_id' => $latestGuidelines?->id,
        ]);

        return [
            'success' => true,
            'message' => 'Thanks. Your report has been received and will be reviewed.',
            'report' => $report,
        ];
    }

    /**
     * Record an administrative moderation action with full audit trail and level/status updates.
     */
    public function recordModerationAction(
        User $admin,
        int $targetUserId,
        string $action,
        ?int $reportId = null,
        ?string $reason = null,
        int $scorePenalty = 0,
        ?string $notes = null
    ): ModerationAction {
        $targetUser = User::findOrFail($targetUserId);

        $previousLevelData = $this->levelService->getUserLevelData($targetUser);
        $previousLevel = $previousLevelData['level'];
        $previousStatus = $targetUser->status->value;

        return DB::transaction(function () use (
            $admin,
            $targetUser,
            $action,
            $reportId,
            $reason,
            $scorePenalty,
            $notes,
            $previousLevel,
            $previousStatus
        ) {
            $actionId = (string) Str::uuid();

            // Apply Level Penalty if specified
            if ($scorePenalty > 0 || $action === 'level_penalty') {
                $penaltyAmount = $scorePenalty > 0 ? $scorePenalty : 100;
                LevelPenalty::create([
                    'user_id' => $targetUser->id,
                    'moderation_action_id' => $actionId,
                    'score_penalty' => $penaltyAmount,
                    'reason' => $reason ?? 'Confirmed moderation violation penalty',
                ]);
            }

            // Apply Account Sanctions
            $newStatus = $previousStatus;
            if ($action === 'suspend') {
                $targetUser->update(['status' => UserStatus::Suspended]);
                $this->spotlightService->revokeUserSpotlights($targetUser);
                $newStatus = UserStatus::Suspended->value;
            } elseif ($action === 'ban') {
                $targetUser->update(['status' => UserStatus::Banned]);
                $this->spotlightService->revokeUserSpotlights($targetUser);
                $newStatus = UserStatus::Banned->value;
            } elseif ($action === 'delete_account') {
                $targetUser->update(['status' => UserStatus::Deleted]);
                $this->spotlightService->revokeUserSpotlights($targetUser);
                $newStatus = UserStatus::Deleted->value;
            }

            // Update associated Report if provided
            if ($reportId) {
                UserReport::where('id', $reportId)->update([
                    'status' => in_array($action, ['dismiss', 'no_violation']) ? 'dismissed' : 'actioned',
                    'resolution' => strtoupper($action),
                    'resolved_at' => now(),
                    'resolved_by' => $admin->id,
                    'admin_notes' => $notes,
                ]);
            }

            // Recalculate level post-penalty
            $newLevelData = $this->levelService->getUserLevelData($targetUser->fresh());
            $newLevel = $newLevelData['level'];

            // Create Moderation Action Audit Record
            $modAction = ModerationAction::create([
                'id' => $actionId,
                'admin_id' => $admin->id,
                'target_user_id' => $targetUser->id,
                'report_id' => $reportId,
                'action' => $action,
                'reason' => $reason,
                'score_penalty' => $scorePenalty,
                'previous_level' => $previousLevel,
                'new_level' => $newLevel,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'notes' => $notes,
            ]);

            if ($this->notificationService) {
                if ($action === 'warn') {
                    $this->notificationService->sendAccountWarningNotification($targetUser, $reason ?? 'Community guidelines violation warning.');
                } elseif ($action === 'suspend') {
                    $this->notificationService->sendAccountSuspendedNotification($targetUser, $reason ?? 'Account suspended for policy violations.');
                } elseif ($action === 'ban') {
                    $this->notificationService->sendAccountBannedNotification($targetUser, $reason ?? 'Account permanently banned for severe policy violations.');
                } else {
                    $this->notificationService->sendModerationUpdateNotification($targetUser, $action, $reason ?? 'Moderation review updated.');
                }
            }

            return $modAction;
        });
    }
}
