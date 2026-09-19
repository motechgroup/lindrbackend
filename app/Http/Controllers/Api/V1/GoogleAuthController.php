<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    /**
     * Authenticate user with Google OAuth credentials.
     *
     * POST /api/v1/auth/google
     */
    public function google(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token' => 'nullable|string',
            'google_id' => 'nullable|string',
            'email' => 'nullable|email',
            'name' => 'nullable|string|max:255',
            'avatar' => 'nullable|url',
        ]);

        $result = $this->googleAuthService->authenticate($validated);

        return response()->json([
            'success' => true,
            'message' => 'Authenticated successfully via Google',
            'data' => [
                'token' => $result['token'],
                'user' => $result['user'],
                'is_onboarded' => $result['is_onboarded'],
                'is_new_user' => $result['is_new_user'],
            ],
        ]);
    }
}
