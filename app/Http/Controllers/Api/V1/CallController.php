<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CallBusyException;
use App\Exceptions\CallUnavailableException;
use App\Exceptions\InsufficientTokensException;
use App\Http\Controllers\Controller;
use App\Models\CallSession;
use App\Models\User;
use App\Services\CallService;
use App\Services\LiveKitService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    use ApiResponse;

    public function __construct(public CallService $callService) {}

    public function requestCall(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'receiver_id' => ['required', 'integer', 'exists:users,id'],
            'recipient_id' => ['nullable', 'integer', 'exists:users,id'],
            'call_type' => ['nullable', 'string', 'in:video,audio'],
            'rate_per_minute' => ['nullable', 'integer', 'min:1'],
        ]);

        $receiverId = $validated['receiver_id'] ?? $validated['recipient_id'];
        $receiver = User::findOrFail($receiverId);
        $callType = $validated['call_type'] ?? 'video';
        $rate = $validated['rate_per_minute'] ?? null;

        try {
            $result = $this->callService->requestCall($request->user(), $receiver, $callType, $rate);

            return $this->successResponse(
                $result,
                'Call request initiated successfully.',
                201
            );
        } catch (CallBusyException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'REJECTED_BUSY',
                'message' => $e->getMessage(),
                'tokens_deducted' => 0,
            ], 409);
        } catch (CallUnavailableException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'REJECTED_OFFLINE',
                'message' => $e->getMessage(),
                'tokens_deducted' => 0,
            ], 422);
        } catch (InsufficientTokensException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INSUFFICIENT_TOKENS',
                'message' => $e->getMessage(),
                'tokens_deducted' => 0,
            ], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'INVALID_REQUEST',
                'message' => $e->getMessage(),
                'tokens_deducted' => 0,
            ], 400);
        }
    }

    public function acceptCall(Request $request, CallSession $call): JsonResponse
    {
        try {
            $result = $this->callService->acceptCall($call, $request->user());

            return $this->successResponse($result, 'Call accepted successfully.');
        } catch (\DomainException|\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (CallUnavailableException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    public function declineCall(Request $request, CallSession $call): JsonResponse
    {
        try {
            $session = $this->callService->declineCall($call, $request->user());

            return $this->successResponse($session, 'Call declined successfully.');
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        }
    }

    public function cancelCall(Request $request, CallSession $call): JsonResponse
    {
        $session = $this->callService->endCall($call, 'CANCELLED');

        return $this->successResponse($session, 'Call cancelled successfully.');
    }

    public function status(Request $request, CallSession $call): JsonResponse
    {
        // Ringing timeout reconciliation (30 seconds)
        if ($call->status === CallSession::STATUS_RINGING && $call->created_at < now()->subSeconds(30)) {
            $call = $this->callService->endCall($call, 'MISSED');
        }

        $livekitData = null;
        if ($call->status === CallSession::STATUS_CONNECTED) {
            $livekitData = app(LiveKitService::class)->generateJoinToken($request->user(), $call->room_name);
        }

        return $this->successResponse([
            'id' => (string) $call->id,
            'status' => match ($call->status) {
                CallSession::STATUS_ENDED => (int) ($call->duration_seconds ?? 0) > 0 ? 'COMPLETED' : 'ENDED',
                CallSession::STATUS_CANCELLED => match ($call->end_reason) {
                    'DECLINED' => 'DECLINED',
                    'MISSED' => 'MISSED',
                    default => 'CANCELLED',
                },
                CallSession::STATUS_CONNECTED => 'CONNECTED',
                CallSession::STATUS_RINGING => 'RINGING',
                default => strtoupper($call->status),
            },
            'end_reason' => $call->end_reason,
            'livekit_url' => $livekitData['ws_url'] ?? null,
            'livekit_token' => $livekitData['token'] ?? null,
            'call_session' => $call->fresh(['caller', 'receiver']),
        ], 'Call status retrieved.');
    }

    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();

        // Reconcile stale ringing calls for user
        $staleRinging = CallSession::where('receiver_id', $user->id)
            ->where('status', CallSession::STATUS_RINGING)
            ->where('created_at', '<', now()->subSeconds(30))
            ->get();

        foreach ($staleRinging as $stale) {
            $this->callService->endCall($stale, 'MISSED');
        }

        $pendingCall = CallSession::where('receiver_id', $user->id)
            ->where('status', CallSession::STATUS_RINGING)
            ->with(['caller.profile', 'caller.photos'])
            ->latest()
            ->first();

        if (! $pendingCall) {
            return $this->successResponse(null, 'No pending incoming calls.');
        }

        return $this->successResponse([
            'id' => (string) $pendingCall->id,
            'caller_id' => $pendingCall->caller_id,
            'caller_name' => $pendingCall->caller?->name ?? 'User',
            'caller_avatar' => $pendingCall->caller?->avatar ?? ($pendingCall->caller?->photos[0]->photo_url ?? null),
            'call_type' => $pendingCall->call_type,
            'created_at' => $pendingCall->created_at?->toIso8601String(),
        ], 'Pending incoming call retrieved.');
    }

    public function pingCall(Request $request, CallSession $call): JsonResponse
    {
        $minute = (int) $request->input('minute_number', 1);
        $result = $this->callService->billCallInterval($call, $minute);

        return $this->successResponse($result, 'Call session heartbeat processed.');
    }

    public function endCall(Request $request, CallSession $call): JsonResponse
    {
        $reason = $request->input('reason', 'USER_DISCONNECTED');
        $session = $this->callService->endCall($call, $reason);

        return $this->successResponse(
            $session,
            'Call session ended successfully.'
        );
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $history = CallSession::where('caller_id', $user->id)
            ->orWhere('receiver_id', $user->id)
            ->with(['caller.profile', 'receiver.profile', 'caller.photos', 'receiver.photos'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $transformed = $history->getCollection()->map(function (CallSession $session) use ($user) {
            $isCaller = $session->caller_id === $user->id;
            $otherUser = $isCaller ? $session->receiver : $session->caller;
            $durationSeconds = (int) ($session->duration_seconds ?? 0);

            $minutes = (int) floor($durationSeconds / 60);
            $seconds = (int) ($durationSeconds % 60);
            $formattedDuration = $minutes > 0 ? "{$minutes} min {$seconds} sec" : "{$seconds} sec";

            $isMatch = str_contains($session->room_name ?? '', 'lindr_room_') || $session->coins_charged == 50;

            $statusLabel = match ($session->status) {
                CallSession::STATUS_ENDED => $durationSeconds > 0 ? 'COMPLETED' : 'ENDED',
                CallSession::STATUS_CANCELLED => match ($session->end_reason) {
                    'DECLINED' => 'DECLINED',
                    'MISSED' => 'MISSED',
                    default => 'CANCELLED',
                },
                CallSession::STATUS_CONNECTED => 'CONNECTED',
                CallSession::STATUS_RINGING => 'RINGING',
                default => strtoupper($session->status),
            };

            return [
                'id' => (string) $session->id,
                'call_type' => $session->call_type ?? 'video',
                'category' => $isMatch ? 'MATCH' : 'DIRECT',
                'direction' => $isCaller ? 'outgoing' : 'incoming',
                'status' => $statusLabel,
                'duration_seconds' => $durationSeconds,
                'formatted_duration' => $formattedDuration,
                'tokens_spent' => $isCaller ? (int) $session->coins_charged : 0,
                'credits_earned' => (! $isCaller && $user->isCreatorVerified()) ? (int) $session->creator_credits_earned : 0,
                'rate_per_minute' => (int) $session->rate_per_minute,
                'created_at' => $session->created_at?->toIso8601String(),
                'started_at' => $session->started_at?->toIso8601String(),
                'other_user' => $otherUser ? [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'avatar' => $otherUser->avatar,
                    'gender' => $otherUser->role?->value ?? (string) $otherUser->role,
                    'level' => $otherUser->profile?->level ?? 1,
                    'is_verified' => (bool) ($otherUser->profile?->is_verified ?? false),
                ] : null,
            ];
        });

        $history->setCollection($transformed);

        return $this->successResponse($history, 'Call history retrieved successfully.');
    }

    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isCreatorVerified()) {
            return $this->successResponse([
                'today' => 0,
                'this_week' => 0,
                'this_month' => 0,
                'total' => 0,
                'is_creator' => false,
            ], 'Call earnings retrieved.');
        }

        $today = (int) CallSession::where('receiver_id', $user->id)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('creator_credits_earned');

        $thisWeek = (int) CallSession::where('receiver_id', $user->id)
            ->where('created_at', '>=', now()->startOfWeek())
            ->sum('creator_credits_earned');

        $thisMonth = (int) CallSession::where('receiver_id', $user->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('creator_credits_earned');

        $total = (int) CallSession::where('receiver_id', $user->id)
            ->sum('creator_credits_earned');

        return $this->successResponse([
            'today' => $today,
            'this_week' => $thisWeek,
            'this_month' => $thisMonth,
            'total' => $total,
            'is_creator' => true,
        ], 'Call earnings retrieved.');
    }
}
