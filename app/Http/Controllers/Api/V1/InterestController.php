<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Interest;
use App\Models\PlatformSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class InterestController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/interests
     * Retrieve active interests catalog for profile editing and discovery filters.
     */
    public function index(): JsonResponse
    {
        $interests = Interest::active()
            ->select(['id', 'name', 'slug', 'category', 'sort_order'])
            ->get();

        $maxAllowed = (int) PlatformSetting::get('max_user_interests', 10);

        return response()->json([
            'success' => true,
            'message' => 'Interests catalog retrieved.',
            'max_allowed' => $maxAllowed,
            'data' => $interests,
        ]);
    }
}
