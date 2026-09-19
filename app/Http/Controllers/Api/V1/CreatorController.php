<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CreatorEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreatorController extends Controller
{
    public function __construct(
        protected CreatorEligibilityService $eligibilityService
    ) {}

    /**
     * Submit gender-neutral creator application.
     *
     * POST /api/v1/creator/apply
     */
    public function apply(Request $request): JsonResponse
    {
        $user = $request->user();

        $errors = $this->eligibilityService->getEligibilityIssues($user);
        if (! empty($errors)) {
            return response()->json([
                'success' => false,
                'message' => 'Creator application requirements not met.',
                'errors' => $errors,
            ], 422);
        }

        if ($user->is_creator && $user->creator_status === 'approved') {
            return response()->json([
                'success' => true,
                'message' => 'User is already an active verified creator.',
                'data' => [
                    'is_creator' => true,
                    'creator_status' => 'approved',
                ],
            ]);
        }

        $user->update([
            'creator_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Creator application initialized. Please complete selfie liveness verification.',
            'data' => [
                'is_creator' => $user->is_creator,
                'creator_status' => 'pending',
            ],
        ]);
    }

    /**
     * Check user's creator status and eligibility requirements.
     *
     * GET /api/v1/creator/status
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'is_creator' => (bool) $user->is_creator,
                'creator_status' => $user->creator_status ?? 'none',
                'liveness_verified_at' => $user->liveness_verified_at?->toIso8601String(),
                'mpesa_phone' => $user->mpesa_phone,
                'mpesa_phone_verified' => (bool) $user->mpesa_phone_verified,
                'has_payout_hold' => $user->hasActivePayoutHold(),
                'payout_hold_until' => $user->payout_hold_until?->toIso8601String(),
                'eligibility_issues' => $this->eligibilityService->getEligibilityIssues($user),
            ],
        ]);
    }
}
