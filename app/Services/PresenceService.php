<?php

namespace App\Services;

use App\Models\CallSession;
use App\Models\User;
use App\Models\UserProfile;

class PresenceService
{
    /**
     * Update or record heartbeat for a user.
     */
    /**
     * Reconcile any stale ringing or connected call sessions for a given user.
     * Returns true if there is still a REAL active call in progress.
     */
    public function reconcileStaleCallsForUser(User $user): bool
    {
        $sessions = CallSession::where(function ($q) use ($user) {
            $q->where('caller_id', $user->id)
                ->orWhere('receiver_id', $user->id);
        })
            ->whereIn('status', ['initiated', 'active', CallSession::STATUS_RINGING, CallSession::STATUS_CONNECTED])
            ->get();

        $hasRealActiveCall = false;

        foreach ($sessions as $session) {
            $isRingingStale = in_array($session->status, ['initiated', CallSession::STATUS_RINGING])
                && $session->created_at->lt(now()->subSeconds(30));

            $lastUpdated = $session->updated_at ?? $session->connected_at ?? $session->started_at;
            $isConnectedStale = in_array($session->status, ['active', CallSession::STATUS_CONNECTED])
                && ($lastUpdated ? $lastUpdated->lt(now()->subMinutes(2)) : true);

            if ($isRingingStale) {
                app(CallService::class)->endCall($session, 'MISSED');
            } elseif ($isConnectedStale) {
                app(CallService::class)->endCall($session, 'TIMEOUT_RECONCILED');
            } else {
                $hasRealActiveCall = true;
            }
        }

        return $hasRealActiveCall;
    }

    /**
     * Update or record heartbeat for a user.
     */
    public function updateHeartbeat(User $user): array
    {
        $profile = UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => explode(' ', $user->name)[0] ?? 'User']
        );

        $inActiveCall = $this->reconcileStaleCallsForUser($user);

        $newStatus = $inActiveCall ? 'busy' : ($profile->online_status === 'offline' ? 'offline' : ($profile->online_status === 'away' ? 'away' : 'available'));

        $profile->update([
            'last_heartbeat_at' => now(),
            'online_status' => $newStatus,
        ]);

        $presence = $this->getUserPresence($user);

        if ($inActiveCall) {
            $activeCall = CallSession::where(function ($q) use ($user) {
                $q->where('caller_id', $user->id)
                    ->orWhere('receiver_id', $user->id);
            })
                ->whereIn('status', ['initiated', 'active', CallSession::STATUS_RINGING, CallSession::STATUS_CONNECTED])
                ->latest('started_at')
                ->first();

            if ($activeCall) {
                $caller = User::find($activeCall->caller_id);
                $presence['active_call'] = [
                    'id' => (string) $activeCall->id,
                    'caller_id' => $activeCall->caller_id,
                    'receiver_id' => $activeCall->receiver_id,
                    'room_name' => $activeCall->room_name,
                    'status' => $activeCall->status,
                    'caller_name' => $caller?->profile?->display_name ?? $caller?->name ?? 'Lindr Member',
                    'livekit_url' => config('livekit.url', 'wss://livekit.lindr.app'),
                ];
            }
        }

        return $presence;
    }

    /**
     * Get real public availability state for a user.
     * Evaluates account status, active call sessions, heartbeat age, and online_status.
     */
    public function getUserPresence(User $user): array
    {
        // 0. Non-active user account status check (banned, suspended, deleted)
        if ($user->status && $user->status->value !== 'active') {
            return [
                'status' => 'offline',
                'is_available' => false,
                'last_seen_at' => null,
            ];
        }

        $profile = $user->profile;

        if (! $profile) {
            return [
                'status' => 'offline',
                'is_available' => false,
                'last_seen_at' => null,
            ];
        }

        // 1. Explicit offline status
        if ($profile->online_status === 'offline') {
            return [
                'status' => 'offline',
                'is_available' => false,
                'last_seen_at' => $profile->last_heartbeat_at?->toIso8601String(),
            ];
        }

        // 2. Heartbeat timeout check (5 minutes)
        if ($profile->last_heartbeat_at && $profile->last_heartbeat_at->lt(now()->subMinutes(5))) {
            return [
                'status' => 'offline',
                'is_available' => false,
                'last_seen_at' => $profile->last_heartbeat_at?->toIso8601String(),
            ];
        }

        // 3. Reconcile stale calls and check real active call status
        $inActiveCall = $this->reconcileStaleCallsForUser($user);

        if ($inActiveCall) {
            return [
                'status' => 'in_call',
                'is_available' => false,
                'last_seen_at' => $profile->last_heartbeat_at?->toIso8601String(),
            ];
        }

        // Self-heal stale busy status if no active call session exists
        if (in_array($profile->online_status, ['busy', 'in_call'])) {
            $profile->update(['online_status' => 'available']);
        }

        // 4. Away status
        if ($profile->online_status === 'away') {
            return [
                'status' => 'away',
                'is_available' => false,
                'last_seen_at' => $profile->last_heartbeat_at?->toIso8601String(),
            ];
        }

        return [
            'status' => 'online',
            'is_available' => true,
            'last_seen_at' => $profile->last_heartbeat_at?->toIso8601String(),
        ];
    }

    /**
     * Explicitly set user presence state.
     */
    public function setPresenceStatus(User $user, string $status): array
    {
        $validStatuses = ['online', 'available', 'away', 'busy', 'in_call', 'offline'];
        if (! in_array(strtolower($status), $validStatuses)) {
            throw new \InvalidArgumentException('Invalid presence status');
        }

        $profile = UserProfile::firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => explode(' ', $user->name)[0] ?? 'User']
        );

        $normalized = match (strtolower($status)) {
            'available', 'online' => 'available',
            'away' => 'away',
            'in_call', 'busy' => 'busy',
            'offline' => 'offline',
            default => 'available',
        };

        $profile->update([
            'online_status' => $normalized,
            'last_heartbeat_at' => now(),
        ]);

        return $this->getUserPresence($user);
    }
}
