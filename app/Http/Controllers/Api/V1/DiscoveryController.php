<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\DiscoveryService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    use ApiResponse;

    public function __construct(public DiscoveryService $discoveryService) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['city', 'gender', 'min_age', 'max_age']);
        $paginated = $this->discoveryService->getDiscoverableProfiles(
            $request->user(),
            $filters,
            (int) $request->input('per_page', 15)
        );

        return $this->paginatedResponse(
            UserResource::collection($paginated),
            'Discoverable profiles fetched successfully.'
        );
    }
}
