<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientTokensException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserMatchResource;
use App\Http\Resources\UserResource;
use App\Services\MatchingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    use ApiResponse;

    public function __construct(public MatchingService $matchingService) {}

    public function index(Request $request): JsonResponse
    {
        $matches = $this->matchingService->getUserMatches($request->user());

        return $this->successResponse(
            UserMatchResource::collection($matches),
            'Matches retrieved successfully.'
        );
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $result = $this->matchingService->searchInstantMatch($request->user());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'code' => $result['code'],
                    'message' => $result['message'],
                    'data' => null,
                ], 200);
            }

            return $this->successResponse([
                'match' => new UserMatchResource($result['match']),
                'target_user' => new UserResource($result['target_user']),
                'call_session' => $result['call_session'],
                'livekit' => $result['livekit'] ?? null,
            ], $result['message']);

        } catch (InsufficientTokensException $e) {
            return response()->json([
                'success' => false,
                'code' => 'INSUFFICIENT_TOKENS',
                'message' => $e->getMessage(),
            ], 402);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
