<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CommunityGuidelinesService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class GuidelinesController extends Controller
{
    use ApiResponse;

    public function __construct(public CommunityGuidelinesService $guidelinesService) {}

    public function latest(): JsonResponse
    {
        $guidelines = $this->guidelinesService->getLatestPublished();

        return $this->successResponse($guidelines, 'Community guidelines retrieved successfully.');
    }
}
