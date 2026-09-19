<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingService
{
    /**
     * Map of common ISO-2 country codes to country names.
     */
    protected array $countryMap = [
        'KE' => 'Kenya',
        'UG' => 'Uganda',
        'TZ' => 'Tanzania',
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'ZA' => 'South Africa',
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AE' => 'United Arab Emirates',
    ];

    /**
     * Complete fast 3-step user onboarding.
     *
     * @param  array  $payload  ['date_of_birth' => string, 'gender' => string, 'country' => ?string, 'country_code' => ?string, 'device_country_code' => ?string]
     */
    public function completeOnboarding(User $user, array $payload, ?string $clientIp = null, ?string $headerCountry = null): array
    {
        // 1. Authoritative Backend Age Verification
        $dob = Carbon::parse($payload['date_of_birth']);
        $age = $dob->age;

        if ($age < 18) {
            throw ValidationException::withMessages([
                'date_of_birth' => ['Lindr is for adults 18 and over.'],
            ]);
        }

        // 2. Gender Normalization
        $rawGender = strtolower(trim($payload['gender']));
        $gender = match ($rawGender) {
            'woman', 'female' => 'female',
            'man', 'male' => 'male',
            default => throw ValidationException::withMessages([
                'gender' => ['Please select a valid gender option (Woman or Man).'],
            ]),
        };

        // 3. Automatic Non-GPS Country Resolution
        $resolvedCountry = $this->resolveCountry($payload, $headerCountry);

        return DB::transaction(function () use ($user, $dob, $gender, $resolvedCountry) {
            // Update User Role matching gender
            $userRole = $gender === 'female' ? UserRole::Female : UserRole::Male;
            $user->update([
                'role' => $userRole,
            ]);

            // Update or Create UserProfile
            $profile = UserProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->profile?->display_name ?? explode(' ', $user->name)[0],
                    'date_of_birth' => $dob->toDateString(),
                    'gender' => $gender,
                    'country' => $resolvedCountry['country_name'],
                    'country_code' => $resolvedCountry['country_code'],
                ]
            );

            return [
                'user' => $user->fresh(['profile', 'wallet', 'photos']),
                'is_onboarded' => true,
            ];
        });
    }

    /**
     * Determine country based on priority:
     * 1. Device locale country code (passed by client)
     * 2. Server header IP country code
     * 3. Unresolved fallback (null)
     */
    public function resolveCountry(array $payload, ?string $headerCountry = null): array
    {
        $rawCode = $payload['country_code'] ??
            $payload['device_country_code'] ??
            $headerCountry;

        if (! $rawCode) {
            return [
                'country_code' => null,
                'country_name' => null,
            ];
        }

        $code = strtoupper(trim($rawCode));

        if (strlen($code) !== 2) {
            return [
                'country_code' => null,
                'country_name' => null,
            ];
        }

        $name = $payload['country'] ?? $this->countryMap[$code] ?? $code;

        return [
            'country_code' => $code,
            'country_name' => $name,
        ];
    }
}
