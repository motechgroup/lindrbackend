<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\CallBusyException;
use App\Exceptions\CallUnavailableException;
use App\Exceptions\InsufficientTokensException;
use App\Models\CallSession;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CallService
{
    public function __construct(
        public PresenceService $presenceService,
        public WalletService $walletService,
        public MonetizationService $monetizationService,
        public CreditLedgerService $creditLedgerService,
        public LiveKitService $liveKitService,
        public BlockService $blockService,
        public GenderEligibilityService $genderEligibilityService,
        public SafetyService $safetyService,
        public NotificationService $notificationService
    ) {}

    /**
     * Atomically request and reserve a call session between caller and receiver.
     */
    public function requestCall(User $caller, User $receiver, string $callType = 'video', ?int $ratePerMinute = null): array
    {
        if ($caller->id === $receiver->id) {
            throw new \InvalidArgumentException('You cannot call yourself.');
        }

        $safetyCheck = $this->safetyService->canCall($caller, $receiver);
        if (! $safetyCheck['allowed']) {
            throw new CallUnavailableException($safetyCheck['reason']);
        }

        $rate = $ratePerMinute ?? (int) PlatformSetting::get('video_call_rate_per_minute', 30);

        return DB::transaction(function () use ($caller, $receiver, $callType, $rate) {
            // Lock recipient profile row for update to prevent concurrent callers race condition
            $receiverProfile = UserProfile::where('user_id', $receiver->id)->lockForUpdate()->first();
            if (! $receiverProfile) {
                $receiverProfile = UserProfile::create([
                    'user_id' => $receiver->id,
                    'display_name' => explode(' ', $receiver->name)[0] ?? 'User',
                ]);
            }

            // Lock caller profile row
            $callerProfile = UserProfile::where('user_id', $caller->id)->lockForUpdate()->first();
            if (! $callerProfile) {
                $callerProfile = UserProfile::create([
                    'user_id' => $caller->id,
                    'display_name' => explode(' ', $caller->name)[0] ?? 'User',
                ]);
            }

            // Check if recipient is already in an active or ringing call session
            $receiverInActiveCall = CallSession::where(function ($q) use ($receiver) {
                $q->where('caller_id', $receiver->id)
                    ->orWhere('receiver_id', $receiver->id);
            })
                ->whereIn('status', [CallSession::STATUS_RINGING, CallSession::STATUS_CONNECTED, 'initiated', 'active'])
                ->exists();

            if ($receiverInActiveCall || $receiverProfile->online_status === 'busy') {
                $receiverName = $receiverProfile->display_name ?? $receiver->name;
                throw new CallBusyException("{$receiverName} is currently on another call.");
            }

            // Check recipient presence (must be active with recent heartbeat)
            $receiverPresence = $this->presenceService->getUserPresence($receiver);
            if ($receiverPresence['status'] === 'offline') {
                $receiverName = $receiverProfile->display_name ?? $receiver->name;
                throw new CallUnavailableException("{$receiverName} is currently offline.");
            }

            // Check caller pre-call token balance (minimum 1 minute required)
            $callerWallet = $this->walletService->getWallet($caller);
            if ($callerWallet->coin_balance < $rate) {
                throw new InsufficientTokensException('Insufficient token balance to initiate call.');
            }

            // Reserve both caller and receiver as BUSY
            $receiverProfile->update([
                'online_status' => 'busy',
                'last_heartbeat_at' => now(),
            ]);

            $callerProfile->update([
                'online_status' => 'busy',
                'last_heartbeat_at' => now(),
            ]);

            // Unique, non-predictable LiveKit room name
            $roomName = 'lindr_call_'.Str::uuid();

            // Create Call Session with rate lock in RINGING status
            $callSession = CallSession::create([
                'id' => (string) Str::uuid(),
                'caller_id' => $caller->id,
                'receiver_id' => $receiver->id,
                'call_type' => $callType,
                'room_name' => $roomName,
                'rate_per_minute' => $rate,
                'status' => CallSession::STATUS_RINGING,
                'started_at' => now(),
                'coins_charged' => 0,
            ]);

            // Send INCOMING_DIRECT_CALL notification to receiver (does NOT auto-accept)
            $this->notificationService->notifyIncomingDirectCall($receiver, $caller, $callSession->id);

            return [
                'id' => (string) $callSession->id,
                'call_session_id' => (string) $callSession->id,
                'room_name' => $roomName,
                'rate_per_minute' => $rate,
                'status' => $callSession->status,
                'livekit_url' => null,
                'livekit_token' => null,
                'call_session' => $callSession->fresh(['caller', 'receiver']),
                'livekit' => null,
            ];
        });
    }

    /**
     * Backward-compatible alias method for initiating call sessions.
     */
    public function initiateCallSession(User $caller, User $receiver, string $callType = 'video', ?int $ratePerMinute = null): array
    {
        return $this->requestCall($caller, $receiver, $callType, $ratePerMinute);
    }

    /**
     * Accept an incoming call and connect LiveKit room.
     */
    public function acceptCall(CallSession $callSession, User $recipient): array
    {
        if ($callSession->caller_id !== $recipient->id && $callSession->receiver_id !== $recipient->id) {
            throw new \DomainException('Unauthorized to access this call session.');
        }

        if (! in_array($callSession->status, [CallSession::STATUS_RINGING, CallSession::STATUS_CONNECTED, 'initiated', 'active'])) {
            throw new CallUnavailableException('Call session is no longer active.');
        }

        return DB::transaction(function () use ($callSession, $recipient) {
            if ($callSession->status !== CallSession::STATUS_CONNECTED) {
                $callSession->update([
                    'status' => CallSession::STATUS_CONNECTED,
                    'connected_at' => now(),
                ]);

                $caller = $callSession->caller;
                if ($caller && $caller->id !== $recipient->id) {
                    $this->notificationService->notifyMatchAccepted($caller, $recipient, $callSession->id);
                }
            }

            // Execute 1st minute billable interval upon connection
            $billingResult = $this->billCallInterval($callSession, 1);

            // Generate LiveKit join token for participant
            $livekitData = $this->liveKitService->generateJoinToken($recipient, $callSession->room_name);

            return [
                'call_session_id' => $callSession->id,
                'room_name' => $callSession->room_name,
                'status' => CallSession::STATUS_CONNECTED,
                'livekit_url' => $livekitData['ws_url'],
                'livekit_token' => $livekitData['token'],
                'call_session' => $callSession->fresh(['caller', 'receiver']),
                'livekit' => $livekitData,
                'billing' => $billingResult,
            ];
        });
    }

    /**
     * Server-authoritative interval billing method with idempotency protection.
     */
    public function billCallInterval(CallSession $callSession, int $minuteNumber): array
    {
        $idempotencyKey = "call_billing_{$callSession->id}_min_{$minuteNumber}";

        // Idempotency check: check if this interval was already charged
        $alreadyCharged = WalletTransaction::where('reference_id', $idempotencyKey)->exists();
        if ($alreadyCharged) {
            return [
                'billed' => true,
                'minute' => $minuteNumber,
                'idempotent_skip' => true,
            ];
        }

        $caller = $callSession->caller;
        $receiver = $callSession->receiver;

        if (! $caller || ! $receiver) {
            return ['billed' => false, 'reason' => 'PARTICIPANT_NOT_FOUND'];
        }

        $rate = $callSession->rate_per_minute;
        $callerWallet = $this->walletService->getWallet($caller);

        // Check if caller has sufficient token balance for this interval
        if ($callerWallet->coin_balance < $rate) {
            // Gracefully terminate call due to insufficient token balance
            $this->endCall($callSession, 'INSUFFICIENT_TOKENS');

            return [
                'billed' => false,
                'reason' => 'INSUFFICIENT_TOKENS',
                'call_ended' => true,
            ];
        }

        return DB::transaction(function () use ($callSession, $caller, $receiver, $rate, $minuteNumber, $idempotencyKey) {
            // Debit caller tokens
            $walletTx = $this->walletService->debitCoins(
                $caller,
                $rate,
                TransactionType::Debit,
                CallSession::class,
                $idempotencyKey,
                "Video call minute {$minuteNumber} to {$receiver->name}"
            );

            $creatorCredits = 0;
            $platformShare = $rate;
            $creatorSharePct = 0.0;

            // Calculate Creator Credit allocation if recipient is a verified creator
            if ($receiver->isCreatorVerified() && $rate > 0) {
                $receiverProfile = $receiver->profile;
                $gender = strtolower($receiverProfile?->gender ?? 'female');

                $split = $this->monetizationService->calculateSplit($rate, 'call', $gender, $receiver);
                $creatorCredits = $split['creator_amount'];
                $platformShare = $split['platform_amount'];
                $creatorSharePct = $split['creator_share_pct'];

                if ($creatorCredits > 0) {
                    $callerName = $caller->profile?->display_name ?? $caller->name;
                    $this->creditLedgerService->creditCreator(
                        $receiver,
                        $creatorCredits,
                        'call',
                        $idempotencyKey,
                        "Video call earnings minute {$minuteNumber} from {$callerName}",
                        [
                            'caller_id' => $caller->id,
                            'call_session_id' => $callSession->id,
                            'minute_number' => $minuteNumber,
                            'gross_tokens' => $rate,
                            'creator_share_pct' => $creatorSharePct,
                        ]
                    );

                    $this->notificationService->notifyCallEarning($receiver, $creatorCredits, $callerName);
                }
            }

            $callSession->increment('coins_charged', $rate);
            $callSession->increment('creator_credits_earned', $creatorCredits);
            $callSession->increment('lindr_share', $platformShare);
            $callSession->update(['creator_commission_pct' => $creatorSharePct]);

            return [
                'billed' => true,
                'minute' => $minuteNumber,
                'rate' => $rate,
                'creator_credits' => $creatorCredits,
                'platform_share' => $platformShare,
            ];
        });
    }

    /**
     * Decline an incoming call request.
     */
    public function declineCall(CallSession $callSession, User $recipient): CallSession
    {
        if ($callSession->receiver_id !== $recipient->id) {
            throw new \DomainException('Unauthorized to decline this call session.');
        }

        return DB::transaction(function () use ($callSession) {
            $callSession->update([
                'status' => CallSession::STATUS_CANCELLED,
                'end_reason' => 'DECLINED',
                'ended_at' => now(),
                'duration_seconds' => 0,
            ]);

            // Restore presence for caller and receiver
            UserProfile::where('user_id', $callSession->caller_id)
                ->update(['online_status' => 'available', 'last_heartbeat_at' => now()]);

            UserProfile::where('user_id', $callSession->receiver_id)
                ->update(['online_status' => 'available', 'last_heartbeat_at' => now()]);

            return $callSession->fresh(['caller', 'receiver']);
        });
    }

    /**
     * Terminate an active or requested call session.
     */
    public function endCall(CallSession $callSession, ?string $reason = 'USER_DISCONNECTED'): CallSession
    {
        return DB::transaction(function () use ($callSession, $reason) {
            // Idempotency: if call is already finalized, return immediately
            if (in_array($callSession->status, [CallSession::STATUS_ENDED, CallSession::STATUS_CANCELLED])) {
                return $callSession->fresh(['caller', 'receiver']);
            }

            $started = $callSession->connected_at ?? null;
            $duration = 0;
            if ($started) {
                $duration = (int) max(0, now()->diffInSeconds($started));
            }

            $finalStatus = match ($reason) {
                'INSUFFICIENT_TOKENS' => CallSession::STATUS_ENDED,
                'CANCELLED', 'DECLINED', 'MISSED', 'TIMEOUT_RECONCILED' => CallSession::STATUS_CANCELLED,
                default => CallSession::STATUS_ENDED,
            };

            // Reconcile and finalize billing for all billable minutes if call was active
            if ($callSession->connected_at && $duration > 0 && $finalStatus === CallSession::STATUS_ENDED) {
                $totalMinutes = (int) max(1, (int) ceil($duration / 60));
                for ($m = 1; $m <= $totalMinutes; $m++) {
                    $this->billCallInterval($callSession, $m);
                }
            }

            $callSession->update([
                'status' => $finalStatus,
                'ended_at' => now(),
                'duration_seconds' => $duration,
                'end_reason' => $reason,
            ]);

            // Reset presence to available for caller and receiver
            UserProfile::where('user_id', $callSession->caller_id)
                ->update(['online_status' => 'available', 'last_heartbeat_at' => now()]);

            UserProfile::where('user_id', $callSession->receiver_id)
                ->update(['online_status' => 'available', 'last_heartbeat_at' => now()]);

            return $callSession->fresh(['caller', 'receiver']);
        });
    }

    /**
     * Reconcile abandoned/hanging call sessions.
     */
    public function reconcileStaleSessions(): int
    {
        $staleSessions = CallSession::whereIn('status', [CallSession::STATUS_RINGING, CallSession::STATUS_CONNECTED, 'initiated', 'active'])
            ->where('updated_at', '<', now()->subMinutes(3))
            ->get();

        $count = 0;
        foreach ($staleSessions as $session) {
            $this->endCall($session, 'TIMEOUT_RECONCILED');
            $count++;
        }

        return $count;
    }
}
