<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MpesaOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaOtpController extends Controller
{
    public function __construct(
        protected MpesaOtpService $mpesaOtpService
    ) {}

    /**
     * Send M-Pesa phone verification OTP.
     *
     * POST /api/v1/payouts/mpesa/send-otp
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
        ]);

        $user = $request->user();
        $normalizedPhone = $this->mpesaOtpService->normalizePhone($validated['phone']);

        $sent = $this->mpesaOtpService->sendOtp($user, $normalizedPhone);

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa verification OTP sent successfully.',
            'data' => [
                'phone' => $normalizedPhone,
                'expires_in_minutes' => 10,
                // In local/testing environments:
                'otp_code' => app()->environment('local', 'testing') ? $sent->code : null,
            ],
        ]);
    }

    /**
     * Verify M-Pesa phone verification OTP.
     *
     * POST /api/v1/payouts/mpesa/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();
        $normalizedPhone = $this->mpesaOtpService->normalizePhone($validated['phone']);

        $verified = $this->mpesaOtpService->verifyOtp($user, $normalizedPhone, $validated['code']);

        if (! $verified) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'M-Pesa phone number verified successfully for creator payouts.',
            'data' => [
                'user_id' => $user->id,
                'mpesa_phone' => $user->mpesa_phone,
                'mpesa_phone_verified' => true,
                'has_payout_hold' => $user->hasActivePayoutHold(),
                'payout_hold_until' => $user->payout_hold_until?->toIso8601String(),
            ],
        ]);
    }
}
