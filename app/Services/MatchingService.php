<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientTokensException;
use App\Models\CallSession;
use App\Models\Like;
use App\Models\MatchRequest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
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
     * Start a broadcast match request across eligible opposite-gender users.
     */
    public function startMatchBroadcast(User $initiator, ?int $tokenCost = null): array
    {
        $cost = $tokenCost ?? (int) PlatformSetting::get('matching_token_cost', PlatformSetting::get('match_coins', 50));

        // Pre-check initiator token balance
        $wallet = $this->walletService->getWallet($initiator);
        if ($wallet->coin_balance < $cost) {
            throw new InsufficientTokensException('Insufficient tokens to initiate match search.');
        }

        return DB::transaction(function () use ($initiator, $cost) {
            // Deduct match tokens (100% platform revenue, 0 creator credits)
            $walletTx = $this->walletService->debitCoins(
                $initiator,
                $cost,
                TransactionType::Debit,
                MatchRequest::class,
                null,
                'Broadcast Match Request'
            );

            // Determine target gender filter
            $initiatorProfile = $initiator->profile;
            $gender = strtolower($initiatorProfile?->gender ?? ($initiator->role?->value ?? 'male'));
            $targetGender = match ($gender) {
                'male', 'man' => 'female',
                'female', 'woman' => 'male',
                default => 'any',
            };

            $requestId = (string) Str::uuid();
            $matchRequest = MatchRequest::create([
                'id' => $requestId,
                'initiator_id' => $initiator->id,
                'status' => 'broadcasting',
                'gender_filter' => $targetGender,
                'token_cost' => $cost,
                'declined_user_ids' => [],
                'expires_at' => now()->addSeconds(30),
            ]);

            $walletTx->update(['reference_id' => $requestId]);

            return [
                'success' => true,
                'code' => 'SEARCHING',
                'message' => 'Searching for an eligible match...',
                'match_request_id' => $requestId,
                'status' => 'broadcasting',
                'expires_in_seconds' => 30,
            ];
        });
    }

    /**
     * Get pending broadcast match requests for an eligible receiver.
     */
    public function getPendingMatchRequestsForUser(User $receiver): array
    {
        $receiverProfile = $receiver->profile;
        if (! $receiverProfile || $receiverProfile->online_status === 'busy') {
            return [];
        }

        $receiverGender = strtolower($receiverProfile->gender ?? ($receiver->role?->value ?? 'female'));
        $targetFilter = match ($receiverGender) {
            'male', 'man' => 'male',
            'female', 'woman' => 'female',
            default => 'any',
        };

        $blockedUserIds = $this->blockService->getBlockedUserIds($receiver);

        $pendingRequests = MatchRequest::where('status', 'broadcasting')
            ->where('initiator_id', '!=', $receiver->id)
            ->whereNotIn('initiator_id', $blockedUserIds)
            ->where('expires_at', '>', now())
            ->where(function ($q) use ($targetFilter) {
                $q->where('gender_filter', $targetFilter)
                    ->orWhere('gender_filter', 'any');
            })
            ->with(['initiator.profile', 'initiator.photos'])
            ->latest()
            ->get();

        $result = [];
        foreach ($pendingRequests as $req) {
            $declinedIds = (array) ($req->declined_user_ids ?? []);
            if (in_array($receiver->id, $declinedIds)) {
                continue;
            }

            $initiator = $req->initiator;
            if (! $initiator || ! $this->safetyService->canMatch($receiver, $initiator)) {
                continue;
            }

            $result[] = [
                'id' => (string) $req->id,
                'match_request_id' => (string) $req->id,
                'initiator_id' => $initiator->id,
                'initiator_name' => $initiator->profile?->display_name ?? $initiator->name ?? 'Lindr Member',
                'initiator_avatar' => $initiator->avatar ?? ($initiator->photos[0]->photo_url ?? null),
                'country' => $initiator->profile?->country ?? 'KE',
                'country_code' => $initiator->profile?->country_code ?? 'KE',
                'created_at' => $req->created_at?->toIso8601String(),
            ];
        }

        return $result;
    }

    /**
     * Atomically accept a broadcast match request (FIRST ACCEPT WINS).
     */
    public function acceptMatchRequest(User $receiver, string $requestId): array
    {
        return DB::transaction(function () use ($receiver, $requestId) {
            /** @var MatchRequest|null $matchRequest */
            $matchRequest = MatchRequest::where('id', $requestId)->lockForUpdate()->first();

            if (! $matchRequest) {
                return [
                    'success' => false,
                    'code' => 'MATCH_NOT_FOUND',
                    'message' => 'Match request does not exist.',
                ];
            }

            if ($matchRequest->status === 'matched') {
                return [
                    'success' => false,
                    'code' => 'MATCH_NO_LONGER_AVAILABLE',
                    'message' => 'Match claimed by another user.',
                ];
            }

            if ($matchRequest->status !== 'broadcasting' && $matchRequest->status !== 'searching') {
                return [
                    'success' => false,
                    'code' => 'MATCH_INACTIVE',
                    'message' => "Match request is {$matchRequest->status}.",
                ];
            }

            if ($matchRequest->isExpired()) {
                $matchRequest->update(['status' => 'expired']);

                return [
                    'success' => false,
                    'code' => 'MATCH_EXPIRED',
                    'message' => 'Match request has expired.',
                ];
            }

            // Verify receiver is eligible
            $initiator = User::with(['profile', 'photos'])->find($matchRequest->initiator_id);
            if (! $initiator || ! $this->safetyService->canMatch($receiver, $initiator)) {
                return [
                    'success' => false,
                    'code' => 'MATCH_INELIGIBLE',
                    'message' => 'You are not eligible for this match.',
                ];
            }

            // ATOMIC LOCK SUCCESS — FIRST ACCEPT WINS!
            $matchRequest->update([
                'status' => 'matched',
                'matched_user_id' => $receiver->id,
            ]);

            // Create UserMatch record
            $userLowId = min($initiator->id, $receiver->id);
            $userHighId = max($initiator->id, $receiver->id);
            UserMatch::firstOrCreate([
                'user_low_id' => $userLowId,
                'user_high_id' => $userHighId,
            ], [
                'matched_at' => now(),
            ]);

            // Create CallSession record in CONNECTED state
            $roomName = 'lindr_room_'.Str::uuid();
            $callSession = CallSession::create([
                'id' => (string) Str::uuid(),
                'caller_id' => $initiator->id,
                'receiver_id' => $receiver->id,
                'call_type' => 'video',
                'room_name' => $roomName,
                'rate_per_minute' => 20,
                'status' => CallSession::STATUS_CONNECTED,
                'started_at' => now(),
                'coins_charged' => $matchRequest->token_cost,
            ]);

            // Reserve both users' presence
            UserProfile::where('user_id', $initiator->id)->update(['online_status' => 'busy', 'last_heartbeat_at' => now()]);
            UserProfile::where('user_id', $receiver->id)->update(['online_status' => 'busy', 'last_heartbeat_at' => now()]);

            $livekitService = app(LiveKitService::class);
            $livekitData = $livekitService->generateJoinToken($receiver, $roomName);

            return [
                'success' => true,
                'code' => 'MATCHED',
                'message' => 'Match established successfully!',
                'matched_user' => [
                    'id' => $initiator->id,
                    'name' => $initiator->profile?->display_name ?? $initiator->name ?? 'Lindr Member',
                    'avatar' => $initiator->avatar ?? ($initiator->photos[0]->photo_url ?? null),
                    'country' => $initiator->profile?->country ?? 'KE',
                    'country_code' => $initiator->profile?->country_code ?? 'KE',
                ],
                'call_session' => $callSession,
                'livekit' => $livekitData,
            ];
        });
    }

    /**
     * Receiver declines a broadcast match request.
     */
    public function declineMatchRequest(User $receiver, string $requestId): array
    {
        $matchRequest = MatchRequest::find($requestId);
        if ($matchRequest) {
            $declined = (array) ($matchRequest->declined_user_ids ?? []);
            if (! in_array($receiver->id, $declined)) {
                $declined[] = $receiver->id;
                $matchRequest->update(['declined_user_ids' => $declined]);
            }
        }

        return [
            'success' => true,
            'message' => 'Match request declined.',
        ];
    }

    /**
     * Initiator cancels a pending match request.
     */
    public function cancelMatchRequest(User $initiator, string $requestId): array
    {
        $matchRequest = MatchRequest::where('id', $requestId)
            ->where('initiator_id', $initiator->id)
            ->first();

        if ($matchRequest && in_array($matchRequest->status, ['broadcasting', 'searching'])) {
            $matchRequest->update(['status' => 'cancelled']);
        }

        return [
            'success' => true,
            'message' => 'Match request cancelled.',
        ];
    }

    /**
     * Poll status of a match request for initiator.
     */
    public function getMatchRequestStatus(User $initiator, string $requestId): array
    {
        /** @var MatchRequest|null $matchRequest */
        $matchRequest = MatchRequest::where('id', $requestId)
            ->where('initiator_id', $initiator->id)
            ->with(['matchedUser.profile', 'matchedUser.photos'])
            ->first();

        if (! $matchRequest) {
            return [
                'success' => false,
                'code' => 'NOT_FOUND',
                'status' => 'cancelled',
            ];
        }

        if ($matchRequest->status === 'broadcasting' && $matchRequest->isExpired()) {
            $matchRequest->update(['status' => 'expired']);
        }

        if ($matchRequest->status === 'matched' && $matchRequest->matchedUser) {
            $matchedUser = $matchRequest->matchedUser;

            // Fetch call session created for this match
            $callSession = CallSession::where('caller_id', $initiator->id)
                ->where('receiver_id', $matchedUser->id)
                ->latest()
                ->first();

            $livekitData = null;
            if ($callSession) {
                $livekitData = app(LiveKitService::class)->generateJoinToken($initiator, $callSession->room_name);
            }

            return [
                'success' => true,
                'code' => 'MATCHED',
                'status' => 'matched',
                'matched_user' => [
                    'id' => $matchedUser->id,
                    'name' => $matchedUser->profile?->display_name ?? $matchedUser->name ?? 'Lindr Member',
                    'avatar' => $matchedUser->avatar ?? ($matchedUser->photos[0]->photo_url ?? null),
                    'country' => $matchedUser->profile?->country ?? 'KE',
                    'country_code' => $matchedUser->profile?->country_code ?? 'KE',
                ],
                'call_session' => $callSession,
                'livekit' => $livekitData,
            ];
        }

        return [
            'success' => true,
            'code' => strtoupper($matchRequest->status),
            'status' => $matchRequest->status,
        ];
    }

    /**
     * Legacy alias for searchInstantMatch.
     */
    public function searchInstantMatch(User $user, ?int $tokenCost = null): array
    {
        return $this->startMatchBroadcast($user, $tokenCost);
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
