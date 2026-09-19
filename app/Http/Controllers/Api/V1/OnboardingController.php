<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Submit fast 3-step onboarding payload (DOB, Gender, Auto-detected Country).
     *
     * POST /api/v1/auth/onboarding
     */
    public function onboard(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|string|in:male,female,man,woman,Male,Female,Man,Woman',
            'country' => 'nullable|string|max:100',
            'country_code' => 'nullable|string|max:10',
            'device_country_code' => 'nullable|string|max:10',
        ]);

        $user = $request->user();
        $headerCountry = $request->header('CF-IPCountry') ?? $request->header('X-Country-Code');

        $result = $this->onboardingService->completeOnboarding(
            $user,
            $validated,
            $request->ip(),
            $headerCountry
        );

        return response()->json([
            'success' => true,
            'message' => 'Onboarding completed successfully. Welcome to Lindr!',
            'data' => [
                'user' => $result['user'],
                'is_onboarded' => true,
            ],
        ]);
    }
}
