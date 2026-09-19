<?php

namespace App\Services;

use App\Models\MpesaVerificationOtp;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MpesaOtpService
{
    /**
     * Alias for phone normalization.
     */
    public function normalizePhone(string $phone): string
    {
        return $this->normalizePhoneNumber($phone);
    }

    /**
     * Normalize Kenyan phone number to 254XXXXXXXXX format.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 10) {
            return '254'.substr($cleaned, 1);
        }

        if (str_starts_with($cleaned, '7') && strlen($cleaned) === 9) {
            return '254'.$cleaned;
        }

        if (str_starts_with($cleaned, '254') && strlen($cleaned) === 12) {
            return $cleaned;
        }

        return $cleaned;
    }

    /**
     * Generate and issue 6-digit OTP for M-Pesa phone verification.
     */
    public function sendOtp(User $user, string $rawPhone): object
    {
        $phone = $this->normalizePhoneNumber($rawPhone);

        // Check rate limiting: max 5 active OTP requests in last hour
        $recentCount = MpesaVerificationOtp::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recentCount >= 5) {
            throw new \RuntimeException('Too many OTP requests. Please try again in an hour.');
        }

        $otp = (string) random_int(100000, 999999);

        MpesaVerificationOtp::create([
            'user_id' => $user->id,
            'phone_number' => $phone,
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(10),
        ]);

        return (object) [
            'phone' => $phone,
            'phone_number' => $phone,
            'code' => $otp,
            'otp_code' => $otp,
            'expires_in_seconds' => 600,
        ];
    }

    /**
     * Verify OTP code and activate M-Pesa phone number for creator payouts.
     */
    public function verifyOtp(User $user, string $rawPhone, string $otp): bool
    {
        $phone = $this->normalizePhoneNumber($rawPhone);

        $record = MpesaVerificationOtp::where('user_id', $user->id)
            ->where('phone_number', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (! $record) {
            throw new \InvalidArgumentException('Invalid or expired OTP verification code.');
        }

        if ($record->attempts >= 5) {
            throw new \RuntimeException('Maximum verification attempts exceeded. Request a new OTP.');
        }

        $record->increment('attempts');

        if (! Hash::check($otp, $record->otp_hash)) {
            return false;
        }

        $record->update(['verified_at' => now()]);

        // If user changed payout phone number, apply payout hold
        $numberChanged = $user->mpesa_phone && $user->mpesa_phone !== $phone;
        $holdHours = (int) PlatformSetting::get('payout_number_change_hold_hours', '24');

        $user->update([
            'mpesa_phone' => $phone,
            'mpesa_phone_verified' => true,
            'mpesa_verified_at' => now(),
            'payout_hold_until' => $numberChanged ? now()->addHours($holdHours) : null,
        ]);

        return true;
    }
}
