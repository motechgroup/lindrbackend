<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PresenceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    use ApiResponse;

    public function __construct(public PresenceService $presenceService) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $presence = $this->presenceService->updateHeartbeat($request->user());

        return $this->successResponse($presence, 'Heartbeat recorded.');
    }

    public function show(User $user): JsonResponse
    {
        $presence = $this->presenceService->getUserPresence($user);

        return $this->successResponse($presence, 'User presence retrieved.');
    }
}
