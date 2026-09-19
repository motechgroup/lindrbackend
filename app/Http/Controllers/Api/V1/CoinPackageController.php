<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CoinPackageResource;
use App\Models\CoinPackage;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class CoinPackageController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $packages = CoinPackage::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->get();

        return $this->successResponse(
            CoinPackageResource::collection($packages),
            'Coin packages retrieved successfully.'
        );
    }
}
