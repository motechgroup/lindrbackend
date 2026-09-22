<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WithdrawalResource;
use App\Models\PlatformSetting;
use App\Models\Withdrawal;
use App\Services\WithdrawalService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    use ApiResponse;

    public function __construct(public WithdrawalService $withdrawalService) {}

    public function requestPayout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'credits' => ['nullable', 'integer', 'gt:0'],
            'amount_kes' => ['nullable', 'numeric', 'gt:0'],
            'mpesa_number' => ['nullable', 'string', 'max:20'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        if (empty($validated['credits']) && empty($validated['amount_kes'])) {
            return $this->errorResponse('Must specify credits or amount_kes for withdrawal request.', 400);
        }

        // Convert amount_kes to credits if credits not explicitly passed
        $creditsPerUsd = (float) PlatformSetting::get('credits_per_usd', 10.0);
        $credits = $validated['credits'] ?? (int) ceil(($validated['amount_kes'] / 130.0) * $creditsPerUsd);

        try {
            $withdrawal = $this->withdrawalService->requestWithdrawal(
                $request->user(),
                $credits,
                $validated['mpesa_number'] ?? null,
                $validated['idempotency_key'] ?? $request->header('X-Idempotency-Key')
            );
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }

        return $this->successResponse(
            new WithdrawalResource($withdrawal),
            'Withdrawal request submitted successfully.',
            201
        );
    }

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $withdrawals = Withdrawal::where('user_id', $userId)
            ->orWhere('female_user_id', $userId)
            ->latest()
            ->paginate((int) $request->input('per_page', 20));

        return $this->paginatedResponse(
            WithdrawalResource::collection($withdrawals),
            'Withdrawal history retrieved.'
        );
    }

    public function methods(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'payout_method' => $user->payout_method ?? 'mpesa',
            'mpesa_phone' => $user->mpesa_phone,
            'is_verified' => (bool) $user->mpesa_phone_verified,
            'paypal_email' => $user->paypal_email,
            'payout_hold' => (bool) ($user->payout_hold_until && now()->lessThan($user->payout_hold_until)),
        ], 'Withdrawal methods retrieved.');
    }

    public function updateMethod(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payout_method' => ['nullable', 'string', 'in:mpesa,paypal'],
            'mpesa_phone' => ['nullable', 'string', 'max:20'],
            'paypal_email' => ['nullable', 'email', 'max:100'],
        ]);

        $user = $request->user();
        $updates = [];

        if (isset($validated['payout_method'])) {
            $updates['payout_method'] = $validated['payout_method'];
        }
        if (isset($validated['mpesa_phone'])) {
            $updates['mpesa_phone'] = $validated['mpesa_phone'];
        }
        if (isset($validated['paypal_email'])) {
            $updates['paypal_email'] = $validated['paypal_email'];
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        return $this->successResponse([
            'payout_method' => $user->payout_method ?? 'mpesa',
            'mpesa_phone' => $user->mpesa_phone,
            'is_verified' => (bool) $user->mpesa_phone_verified,
            'paypal_email' => $user->paypal_email,
        ], 'Withdrawal method updated successfully.');
    }
}
