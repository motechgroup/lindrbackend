<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiatePaymentRequest;
use App\Models\CoinPackage;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentRouter;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        public PaymentService $paymentService,
        public PaymentRouter $paymentRouter
    ) {}

    /**
     * GET /api/v1/payments/methods
     * Get dynamically available payment methods for user.
     */
    public function methods(Request $request): JsonResponse
    {
        $user = $request->user();
        $country = $this->paymentRouter->detectCountry($user, $request->query('country'));
        $currency = $this->paymentRouter->resolveCurrency($country, $request->query('currency'));

        $availableMethods = $this->paymentRouter->getAvailableMethods($user, $country, $currency);

        $formatted = $availableMethods->map(function ($method) {
            return [
                'code' => $method->code,
                'name' => $method->name,
                'provider' => $method->provider_code,
                'enabled' => (bool) $method->enabled,
                'minimum_amount' => $method->minimum_amount ? (float) $method->minimum_amount : null,
                'maximum_amount' => $method->maximum_amount ? (float) $method->maximum_amount : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'country' => $country,
                'currency' => $currency,
                'methods' => $formatted,
            ],
        ]);
    }

    /**
     * POST /api/v1/payments/initiate
     * Initiate coin purchase transaction.
     */
    public function initiate(InitiatePaymentRequest $request): JsonResponse
    {
        $user = $request->user();
        $package = CoinPackage::findOrFail($request->validated('coin_package_id'));

        try {
            $transaction = $this->paymentService->initiatePurchase(
                $user,
                $package,
                $request->validated('payment_method'),
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => $transaction->status === 'failed' ? $transaction->error_message : 'Payment initiated successfully.',
                'data' => [
                    'public_reference' => $transaction->public_reference,
                    'provider' => $transaction->provider_code,
                    'payment_method' => $transaction->payment_method_code,
                    'amount' => (float) $transaction->amount,
                    'currency' => $transaction->currency,
                    'expected_coins' => $transaction->expected_coins,
                    'coins_credited' => $transaction->expected_coins,
                    'status' => $transaction->status,
                    'checkout_url' => $transaction->metadata['checkout_url'] ?? null,
                    'action_data' => $transaction->metadata['action_data'] ?? null,
                ],
            ], $transaction->status === 'failed' ? 422 : 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /api/v1/payments/{reference}
     * Check transaction status by public reference.
     */
    public function status(Request $request, string $reference): JsonResponse
    {
        $user = $request->user();
        $transaction = PaymentTransaction::where('user_id', $user->id)
            ->where(function ($query) use ($reference) {
                $query->where('public_reference', $reference)
                    ->orWhere('id', $reference);
            })
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'public_reference' => $transaction->public_reference,
                'provider' => $transaction->provider_code,
                'payment_method' => $transaction->payment_method_code,
                'amount' => (float) $transaction->amount,
                'currency' => $transaction->currency,
                'expected_coins' => $transaction->expected_coins,
                'status' => $transaction->status,
                'provider_reference' => $transaction->provider_reference,
                'error_message' => $transaction->error_message,
                'created_at' => $transaction->created_at->toIso8601String(),
                'updated_at' => $transaction->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/payments
     * Get paginated payment transaction history.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PaymentTransaction::where('user_id', $user->id)->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        $transactions = $query->paginate($request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions->map(function ($tx) {
                return [
                    'public_reference' => $tx->public_reference,
                    'provider' => $tx->provider_code,
                    'payment_method' => $tx->payment_method_code,
                    'amount' => (float) $tx->amount,
                    'currency' => $tx->currency,
                    'expected_coins' => $tx->expected_coins,
                    'status' => $tx->status,
                    'created_at' => $tx->created_at->toIso8601String(),
                ];
            }),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }
}
