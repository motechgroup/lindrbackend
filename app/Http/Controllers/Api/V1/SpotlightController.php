<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientTokensException;
use App\Http\Controllers\Controller;
use App\Services\SpotlightService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpotlightController extends Controller
{
    use ApiResponse;

    public function __construct(public SpotlightService $spotlightService) {}

    public function packages(): JsonResponse
    {
        $packages = $this->spotlightService->getPackages();

        return $this->successResponse($packages, 'Spotlight packages retrieved successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        $activeSpotlight = $this->spotlightService->getActiveSpotlight($request->user());

        return $this->successResponse([
            'is_active' => ! is_null($activeSpotlight),
            'spotlight' => $activeSpotlight,
        ], 'User spotlight status retrieved.');
    }

    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'package_id' => ['required', 'integer', 'exists:spotlight_packages,id'],
        ]);

        try {
            $purchase = $this->spotlightService->purchaseSpotlight(
                $request->user(),
                (int) $request->package_id
            );

            return $this->successResponse($purchase, 'Spotlight boost activated successfully!');
        } catch (InsufficientTokensException $e) {
            return response()->json([
                'success' => false,
                'code' => 'INSUFFICIENT_TOKENS',
                'message' => $e->getMessage(),
            ], 402);
        } catch (\DomainException $e) {
            return $this->errorResponse($e->getMessage(), 403);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}
