<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Map notification type to user preference category.
     */
    public function getCategoryForType(string $type): string
    {
        return match (strtoupper($type)) {
            'NEW_MESSAGE' => 'messages',
            'INCOMING_DIRECT_CALL', 'MISSED_CALL', 'CALL_ENDED' => 'calls',
            'INCOMING_MATCH', 'MATCH_ACCEPTED', 'MATCH_EXPIRED', 'MATCH_UNAVAILABLE' => 'matches',
            'GIFT_RECEIVED' => 'gifts',
            'CALL_EARNING', 'GIFT_EARNING', 'CHAT_EARNING' => 'earnings',
            'WITHDRAWAL_SUBMITTED', 'WITHDRAWAL_PROCESSING', 'WITHDRAWAL_SUCCESS', 'WITHDRAWAL_FAILED' => 'withdrawals',
            'LEVEL_UP' => 'levels',
            'SPOTLIGHT_STARTED', 'SPOTLIGHT_EXPIRING' => 'spotlight',
            'ACCOUNT_WARNING', 'ACCOUNT_SUSPENDED', 'ACCOUNT_BANNED', 'MODERATION_UPDATE' => 'security',
            default => 'system',
        };
    }

    /**
     * Send a notification to a user (database + device push notifications).
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyUser(User $user, string $type, string $title, string $body, array $data = []): ?string
    {
        $category = $this->getCategoryForType($type);

        // Check user notification preference
        if (! $user->hasNotificationEnabled($category)) {
            Log::info("Notification [{$type}] skipped for user #{$user->id} based on user preference.");

            return null;
        }

        $notificationId = (string) Str::uuid();

        // Save to database notifications table
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\SystemNotification',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'title' => $title,
                'body' => $body,
                'notification_type' => strtoupper($type),
                'category' => $category,
                'metadata' => $data,
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Dispatch push notification to user's registered devices
        $this->dispatchDevicePush($user, $title, $body, array_merge(['notification_type' => strtoupper($type), 'id' => $notificationId], $data));

        return $notificationId;
    }

    /**
     * Dispatch push notification to active registered user devices via Expo Push API / FCM.
     *
     * @param  array<string, mixed>  $data
     */
    protected function dispatchDevicePush(User $user, string $title, string $body, array $data = []): int
    {
        $devices = $user->devices;
        if ($devices->isEmpty()) {
            Log::info("Push notification simulated for user #{$user->id}: {$title}");

            return 0;
        }

        $sentCount = 0;
        foreach ($devices as $device) {
            // Log & simulate push dispatch per active device token
            Log::info("Push notification sent to device #{$device->id} ({$device->platform}) for user #{$user->id}: {$title}");
            $device->update(['last_seen_at' => now()]);
            $sentCount++;
        }

        return $sentCount;
    }

    // --- Helper Methods for Notification Types ---

    public function notifyNewMessage(User $recipient, User $sender, string $messagePreview, int|string $conversationId): void
    {
        $senderName = explode(' ', $sender->name)[0];
        $this->notifyUser(
            $recipient,
            'NEW_MESSAGE',
            "New message from {$senderName}",
            $messagePreview,
            ['conversation_id' => (string) $conversationId, 'sender_id' => $sender->id, 'sender_name' => $senderName]
        );
    }

    public function notifyIncomingDirectCall(User $recipient, User $caller, string $callSessionId): void
    {
        $callerName = explode(' ', $caller->name)[0];
        $this->notifyUser(
            $recipient,
            'INCOMING_DIRECT_CALL',
            'Incoming Video Call 📹',
            "{$callerName} is calling you on Lindr.",
            ['call_session_id' => $callSessionId, 'caller_id' => $caller->id, 'caller_name' => $callerName]
        );
    }

    public function notifyMissedCall(User $recipient, User $caller): void
    {
        $callerName = explode(' ', $caller->name)[0];
        $this->notifyUser(
            $recipient,
            'MISSED_CALL',
            'Missed Video Call 📞',
            "You missed a video call from {$callerName}.",
            ['caller_id' => $caller->id, 'caller_name' => $callerName]
        );
    }

    public function notifyIncomingMatch(User $candidate, User $caller): void
    {
        $callerName = explode(' ', $caller->name)[0];
        $this->notifyUser(
            $candidate,
            'INCOMING_MATCH',
            'Instant Match Invitation ✨',
            "{$callerName} is looking for a Match!",
            ['caller_id' => $caller->id, 'caller_name' => $callerName]
        );
    }

    public function notifyMatchAccepted(User $user, User $matchedUser, string $callSessionId): void
    {
        $matchedName = explode(' ', $matchedUser->name)[0];
        $this->notifyUser(
            $user,
            'MATCH_ACCEPTED',
            'Match Connected! 🎉',
            "You matched with {$matchedName}!",
            ['call_session_id' => $callSessionId, 'matched_user_id' => $matchedUser->id, 'matched_user_name' => $matchedName]
        );
    }

    public function notifyGiftReceived(User $recipient, User $sender, string $giftName, int $creditEarnings = 0): void
    {
        $senderName = explode(' ', $sender->name)[0];
        $body = "{$senderName} sent you a {$giftName}!";
        if ($creditEarnings > 0) {
            $body .= " (+{$creditEarnings} credits earned)";
        }

        $this->notifyUser(
            $recipient,
            'GIFT_RECEIVED',
            'Gift Received! 🎁',
            $body,
            ['sender_id' => $sender->id, 'sender_name' => $senderName, 'gift_name' => $giftName, 'credits_earned' => $creditEarnings]
        );
    }

    public function notifyCallEarning(User $creator, int $credits, string $callerName): void
    {
        $this->notifyUser(
            $creator,
            'CALL_EARNING',
            'Call Earnings Received 💎',
            "+{$credits} credits earned from video call with {$callerName}.",
            ['credits' => $credits, 'caller_name' => $callerName]
        );
    }

    public function notifyWithdrawalStatus(User $creator, string $status, int $creditsDeducted): void
    {
        $type = match (strtolower($status)) {
            'processing' => 'WITHDRAWAL_PROCESSING',
            'completed', 'success' => 'WITHDRAWAL_SUCCESS',
            'failed', 'cancelled' => 'WITHDRAWAL_FAILED',
            default => 'WITHDRAWAL_SUBMITTED',
        };

        $title = match ($type) {
            'WITHDRAWAL_SUCCESS' => 'Withdrawal Successful! 💵',
            'WITHDRAWAL_FAILED' => 'Withdrawal Failed ⚠️',
            'WITHDRAWAL_PROCESSING' => 'Withdrawal Processing ⏳',
            default => 'Withdrawal Request Submitted 📋',
        };

        $this->notifyUser(
            $creator,
            $type,
            $title,
            "Your payout request of {$creditsDeducted} credits status is now ".strtoupper($status).'.',
            ['status' => $status, 'credits' => $creditsDeducted]
        );
    }

    public function notifyLevelUp(User $user, int $newLevel, string $levelName): void
    {
        $this->notifyUser(
            $user,
            'LEVEL_UP',
            'Level Up! ⭐',
            "Congratulations! You reached Level {$newLevel} ({$levelName}).",
            ['new_level' => $newLevel, 'level_name' => $levelName]
        );
    }

    public function notifySpotlightStarted(User $user, int $durationMinutes): void
    {
        $this->notifyUser(
            $user,
            'SPOTLIGHT_STARTED',
            'Spotlight Activated! ⚡',
            "Your profile is now boosted at the top of Discover for {$durationMinutes} minutes.",
            ['duration_minutes' => $durationMinutes]
        );
    }

    public function notifyModerationAction(User $user, string $actionType, ?string $reason = null): void
    {
        $type = match ($actionType) {
            'warn', 'warning' => 'ACCOUNT_WARNING',
            'suspend' => 'ACCOUNT_SUSPENDED',
            'ban' => 'ACCOUNT_BANNED',
            default => 'MODERATION_UPDATE',
        };

        $title = match ($type) {
            'ACCOUNT_WARNING' => 'Account Notice ⚠️',
            'ACCOUNT_SUSPENDED' => 'Account Suspended 🔴',
            'ACCOUNT_BANNED' => 'Account Banned 🔴',
            default => 'Moderation Update 🛡️',
        };

        $body = 'Your account has received a moderation update: '.strtoupper($actionType).'.';
        if ($reason) {
            $body .= " Reason: {$reason}";
        }

        $this->notifyUser(
            $user,
            $type,
            $title,
            $body,
            ['action' => $actionType, 'reason' => $reason]
        );
    }

    public function notifyCreatorVerificationApproved(User $user): void
    {
        $this->notifyUser(
            $user,
            'CREATOR_VERIFICATION_APPROVED',
            'Verification Approved! ✅',
            'Your Lindr creator verification has been approved.',
            ['status' => 'approved']
        );
    }

    public function notifyCreatorVerificationRejected(User $user, ?string $reason = null): void
    {
        $body = 'Your verification was not approved.';
        if ($reason) {
            $body .= " Reason: {$reason}";
        }

        $this->notifyUser(
            $user,
            'CREATOR_VERIFICATION_REJECTED',
            'Verification Update ⚠️',
            $body,
            ['status' => 'rejected', 'reason' => $reason]
        );
    }

    public function notifyCreatorVerificationRevoked(User $user, ?string $reason = null): void
    {
        $body = 'Your creator verification status has been changed.';
        if ($reason) {
            $body .= " Reason: {$reason}";
        }

        $this->notifyUser(
            $user,
            'CREATOR_VERIFICATION_REVOKED',
            'Verification Status Changed 🔴',
            $body,
            ['status' => 'revoked', 'reason' => $reason]
        );
    }
}
