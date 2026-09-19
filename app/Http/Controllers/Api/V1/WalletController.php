<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiatePaymentRequest;
use App\Http\Resources\WalletTransactionResource;
use App\Services\WalletService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use ApiResponse;

    public function __construct(public WalletService $walletService) {}

    public function show(Request $request): JsonResponse
    {
        $summary = $this->walletService->getWalletSummary($request->user());

        return $this->successResponse(
            $summary,
            'Wallet summary retrieved.'
        );
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = $this->walletService->getTransactionHistory(
            $request->user(),
            (int) $request->input('per_page', 20)
        );

        return $this->paginatedResponse(
            WalletTransactionResource::collection($transactions),
            'Wallet transaction history retrieved.'
        );
    }

    public function topup(InitiatePaymentRequest $request, PaymentController $paymentController): JsonResponse
    {
        return $paymentController->initiate($request);
    }
}
