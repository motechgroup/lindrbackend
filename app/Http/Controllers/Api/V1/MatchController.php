<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientTokensException;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserMatchResource;
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
        return $this->start($request);
    }

    public function start(Request $request): JsonResponse
    {
        try {
            $result = $this->matchingService->startMatchBroadcast($request->user());

            return $this->successResponse($result, 'Match search broadcast started.');
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

    public function pending(Request $request): JsonResponse
    {
        $requests = $this->matchingService->getPendingMatchRequestsForUser($request->user());

        return $this->successResponse($requests, 'Pending match requests retrieved.');
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $result = $this->matchingService->acceptMatchRequest($request->user(), $id);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'code' => $result['code'] ?? 'MATCH_FAILED',
                'message' => $result['message'] ?? 'Unable to accept match.',
            ], 409);
        }

        return $this->successResponse($result, 'Match accepted successfully.');
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        $result = $this->matchingService->declineMatchRequest($request->user(), $id);

        return $this->successResponse($result, 'Match request declined.');
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $result = $this->matchingService->cancelMatchRequest($request->user(), $id);

        return $this->successResponse($result, 'Match request cancelled.');
    }

    public function status(Request $request, string $id): JsonResponse
    {
        $result = $this->matchingService->getMatchRequestStatus($request->user(), $id);

        return $this->successResponse($result, 'Match request status retrieved.');
    }
}
