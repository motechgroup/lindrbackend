<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MpesaService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        public PaymentService $paymentService,
        public MpesaService $mpesaService
    ) {}

    public function handleKora(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        $processed = $this->paymentService->processWebhook('korapay', $payload, $headers);

        return response()->json([
            'status' => $processed ? 'success' : 'acknowledged',
        ]);
    }

    public function handleFlutterwave(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        $processed = $this->paymentService->processWebhook('flutterwave', $payload, $headers);

        return response()->json([
            'status' => $processed ? 'success' : 'acknowledged',
        ]);
    }

    public function handleMpesa(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        $processed = $this->paymentService->processWebhook('mpesa', $payload, $headers);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Callback processed successfully',
        ]);
    }

    public function handleMpesaB2CResult(Request $request): JsonResponse
    {
        $payload = $request->all();
        $result = $this->mpesaService->handleB2CResult($payload);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'B2C Callback processed successfully',
            'data' => $result,
        ]);
    }

    public function handleMpesaB2CTimeout(Request $request): JsonResponse
    {
        $payload = $request->all();
        $result = $this->mpesaService->handleB2CTimeout($payload);

        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'B2C Timeout logged successfully',
            'data' => $result,
        ]);
    }

    public function handleGooglePay(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        $processed = $this->paymentService->processWebhook('google_pay', $payload, $headers);

        return response()->json([
            'status' => $processed ? 'success' : 'acknowledged',
        ]);
    }
}
