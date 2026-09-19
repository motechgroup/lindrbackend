<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserMatchResource;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SwipeController extends Controller
{
    use ApiResponse;

    public function __construct(public MatchingService $matchingService) {}

    public function swipe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'is_like' => ['required', 'boolean'],
        ]);

        $result = $this->matchingService->recordSwipe(
            $request->user(),
            (int) $validated['target_user_id'],
            (bool) $validated['is_like']
        );

        $message = $result['matched'] ? "It's a Match!" : ($validated['is_like'] ? 'Liked profile.' : 'Passed profile.');

        return $this->successResponse([
            'matched' => $result['matched'],
            'match' => $result['match'] ? new UserMatchResource($result['match']) : null,
        ], $message);
    }
}
