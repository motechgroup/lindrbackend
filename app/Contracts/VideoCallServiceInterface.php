<?php

namespace App\Contracts;

use App\Models\CallSession;
use App\Models\User;

interface VideoCallServiceInterface
{
    public function generateRoomToken(User $user, string $roomName): string;

    public function initiateCallSession(User $caller, User $receiver, string $type = 'audio'): CallSession;

    public function endCallSession(CallSession $session, int $durationSeconds): CallSession;
}
