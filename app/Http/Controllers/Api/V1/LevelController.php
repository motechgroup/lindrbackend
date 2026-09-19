<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LevelRule;
use App\Services\LevelService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LevelController extends Controller
{
    use ApiResponse;

    public function __construct(public LevelService $levelService) {}

    public function me(Request $request): JsonResponse
    {
        $levelData = $this->levelService->getUserLevelData($request->user());

        return $this->successResponse($levelData, 'User level details retrieved successfully.');
    }

    public function progress(Request $request): JsonResponse
    {
        $levelData = $this->levelService->getUserLevelData($request->user());

        return $this->successResponse([
            'current_level' => $levelData['level'],
            'current_score' => $levelData['score'],
            'next_level' => $levelData['next_level'],
            'score_required_for_next_level' => $levelData['next_threshold'],
            'points_to_next_level' => $levelData['points_to_next_level'],
            'progress_percentage' => $levelData['progress_pct'],
            'membership_duration_days' => $levelData['membership_days'] ?? 0,
            'community_standing' => $levelData['community_standing'],
            'benefits' => $levelData['benefits'],
            'metrics' => [
                'topup_points' => $levelData['breakdown']['topup_points'],
                'spotlight_points' => $levelData['breakdown']['spotlight_points'],
                'membership_points' => $levelData['breakdown']['membership_points'],
            ],
        ], 'User level progress retrieved successfully.');
    }

    public function rules(): JsonResponse
    {
        $this->levelService->seedDefaultRulesIfEmpty();

        $rules = LevelRule::where('is_active', true)->orderBy('level', 'asc')->get();

        return $this->successResponse($rules, 'Level rules retrieved successfully.');
    }
}
