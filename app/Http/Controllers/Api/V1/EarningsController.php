<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WithdrawalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EarningsController extends Controller
{
    use ApiResponse;

    public function __construct(
        public WithdrawalService $withdrawalService,
        public CreditController $creditController
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user->isCreatorVerified() && ! $user->is_creator) {
            return $this->errorResponse('Earnings dashboard is reserved for verified creators.', 403);
        }

        return $this->creditController->summary($request);
    }
}
