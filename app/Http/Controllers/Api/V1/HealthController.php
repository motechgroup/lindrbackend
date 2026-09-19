<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    use ApiResponse;

    public function __construct(public HealthCheckService $healthCheckService) {}

    public function __invoke(): JsonResponse
    {
        $healthData = $this->healthCheckService->checkSystemHealth();
        $isHealthy = $healthData['database']['connected'] ?? false;

        $statusCode = $isHealthy ? 200 : 503;

        return $this->successResponse(
            $healthData,
            $isHealthy ? 'Lindr API v1 operational.' : 'Lindr API operating with degraded components.',
            $statusCode
        );
    }
}
