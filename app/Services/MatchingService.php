<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\UserStatus;
use App\Exceptions\InsufficientTokensException;
use App\Models\CallSession;
use App\Models\Like;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MatchingService
{
    public function __construct(
        public WalletService $walletService,
        public BlockService $blockService,
        public GenderEligibilityService $genderEligibilityService,
        public SafetyService $safetyService,
        public NotificationService $notificationService
    ) {}

    /**
     * Search and execute an instant paid match between available users.
     *
     * @return array{success: bool, code: string, message: string, match: ?UserMatch, target_user: ?User, call_session: ?CallSession}
     */
    public function searchInstantMatch(User $user, ?int $tokenCost = null): array
    {
        $cost = $tokenCost ?? (int) PlatformSetting::get('matching_token_cost', 50);

        // 1. Pre-check caller token balance
        $wallet = $this->walletService->getWallet($user);
        if ($wallet->coin_balance < $cost) {
            Log::info('MATCH DEBUG', [
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'wallet_balance' => $wallet->coin_balance,
                'match_cost' => $cost,
                'eligible_candidate' => null,
                'decision' => 'INSUFFICIENT_TOKENS',
            ]);

            throw new InsufficientTokensException('Insufficient tokens to initiate instant match.');
        }

        return DB::transaction(function () use ($user, $cost, $wallet) {
            // Lock caller profile
            $userProfile = UserProfile::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $userProfile) {
                $userProfile = UserProfile::create([
                    'user_id' => $user->id,
                    'display_name' => explode(' ', $user->name)[0] ?? 'User',
                ]);
            }

            // Exclude blocked users
            $blockedUserIds = $this->blockService->getBlockedUserIds($user);

            // Query candidate users with strict opposite gender filter
            $query = User::query()
                ->where('id', '!=', $user->id)
                ->where('status', UserStatus::Active)
                ->whereNotIn('id', $blockedUserIds);

            $query = $this->genderEligibilityService->applyGenderFilter($query, $user);

            // Exclude users currently in an active or initiated call session
            $busyUserIds = CallSession::whereIn('status', ['initiated', 'active', CallSession::STATUS_CONNECTED, CallSession::STATUS_RINGING])
                ->pluck('caller_id')
                ->merge(CallSession::whereIn('status', ['initiated', 'active', CallSession::STATUS_CONNECTED, CallSession::STATUS_RINGING])->pluck('receiver_id'))
                ->unique();

            $query->whereNotIn('id', $busyUserIds);

            // Exclude busy profiles
            $candidateUser = $query->whereHas('profile', function ($q) {
                $q->where('online_status', '!=', 'busy');
            })->inRandomOrder()->lockForUpdate()->first();

            if (! $candidateUser || $candidateUser->id === $user->id || ! $this->safetyService->canMatch($user, $candidateUser)) {
                Log::info('MATCH DEBUG', [
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                    'wallet_balance' => $wallet->coin_balance,
                    'match_cost' => $cost,
                    'eligible_candidate' => null,
                    'decision' => 'NO_MATCH',
                ]);

                return [
                    'success' => false,
                    'code' => 'NO_MATCH_AVAILABLE',
                    'message' => 'No eligible opposite-gender user is available right now.',
                    'match' => null,
                    'target_user' => null,
                    'call_session' => null,
                ];
            }

            Log::info('MATCH DEBUG', [
                'caller_id' => $user->id,
                'caller_name' => $user->name,
                'caller_gender' => $user->role?->value ?? (string) $user->role,
                'receiver_id' => $candidateUser->id,
                'receiver_name' => $candidateUser->name,
                'receiver_gender' => $candidateUser->role?->value ?? (string) $candidateUser->role,
                'match_cost' => $cost,
                'decision' => 'ALLOWED',
            ]);

            // Deduct matching tokens (100% platform revenue, 0 creator credits)
            $walletTx = $this->walletService->debitCoins(
                $user,
                $cost,
                TransactionType::Debit,
                UserMatch::class,
                null,
                "Instant Match with {$candidateUser->name}"
            );

            // Reserve both caller and candidate as busy
            $candidateProfile = UserProfile::where('user_id', $candidateUser->id)->lockForUpdate()->first();
            if ($candidateProfile) {
                $candidateProfile->update(['online_status' => 'busy', 'last_heartbeat_at' => now()]);
            }
            $userProfile->update(['online_status' => 'busy', 'last_heartbeat_at' => now()]);

            // Create UserMatch record
            $userLowId = min($user->id, $candidateUser->id);
            $userHighId = max($user->id, $candidateUser->id);

            $matchRecord = UserMatch::firstOrCreate([
                'user_low_id' => $userLowId,
                'user_high_id' => $userHighId,
            ], [
                'matched_at' => now(),
            ]);

            // Create CallSession record in RINGING status
            $roomName = 'lindr_room_'.Str::uuid();
            $callSession = CallSession::create([
                'id' => (string) Str::uuid(),
                'caller_id' => $user->id,
                'receiver_id' => $candidateUser->id,
                'call_type' => 'video',
                'room_name' => $roomName,
                'rate_per_minute' => 20,
                'status' => CallSession::STATUS_RINGING,
                'started_at' => now(),
                'coins_charged' => $cost,
            ]);

            // Send INCOMING_MATCH notification to candidate user (requires receiver acceptance)
            $this->notificationService->notifyIncomingMatch($candidateUser, $user);

            $walletTx->update(['reference_id' => (string) $matchRecord->id]);

            return [
                'success' => true,
                'code' => 'MATCH_FOUND',
                'message' => 'Match request sent! Waiting for receiver...',
                'match' => $matchRecord->fresh(['userLow.profile', 'userLow.photos', 'userHigh.profile', 'userHigh.photos']),
                'target_user' => $candidateUser->fresh(['profile', 'photos']),
                'call_session' => $callSession,
                'livekit' => null,
            ];
        });
    }

    /**
     * Record a swipe (like or pass) and create a match if mutual like occurs.
     *
     * @return array{matched: bool, match: ?UserMatch, like: Like}
     */
    public function recordSwipe(User $user, int $targetUserId, bool $isLike): array
    {
        if ($user->id === $targetUserId) {
            throw new \InvalidArgumentException('You cannot swipe on yourself.');
        }

        $targetUser = User::findOrFail($targetUserId);
        $check = $this->safetyService->canInteract($user, $targetUser);
        if (! $check['allowed']) {
            throw new \InvalidArgumentException($check['reason']);
        }

        return DB::transaction(function () use ($user, $targetUserId, $isLike) {
            $like = Like::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'target_user_id' => $targetUserId,
                ],
                [
                    'is_like' => $isLike,
                ]
            );

            $matched = false;
            $matchRecord = null;

            if ($isLike) {
                $mutualLike = Like::where('user_id', $targetUserId)
                    ->where('target_user_id', $user->id)
                    ->where('is_like', true)
                    ->exists();

                if ($mutualLike) {
                    $userLowId = min($user->id, $targetUserId);
                    $userHighId = max($user->id, $targetUserId);

                    $matchRecord = UserMatch::firstOrCreate([
                        'user_low_id' => $userLowId,
                        'user_high_id' => $userHighId,
                    ], [
                        'matched_at' => now(),
                    ]);

                    $matched = true;
                }
            }

            return [
                'matched' => $matched,
                'match' => $matchRecord,
                'like' => $like,
            ];
        });
    }

    /**
     * Get all matches for a given user.
     */
    public function getUserMatches(User $user)
    {
        $matches = UserMatch::where('user_low_id', $user->id)
            ->orWhere('user_high_id', $user->id)
            ->with(['userLow.profile', 'userLow.photos', 'userHigh.profile', 'userHigh.photos'])
            ->latest('matched_at')
            ->get();

        return $matches->filter(function ($match) use ($user) {
            $otherUser = $match->user_low_id === $user->id ? $match->userHigh : $match->userLow;

            return $otherUser && $this->safetyService->canDiscover($user, $otherUser);
        })->values();
    }
}
