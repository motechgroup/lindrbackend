<?php

namespace App\Services;

use App\Models\Interest;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Carbon;

class ProfileService
{
    /**
     * Create or update a user's profile with validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateOrCreateProfile(User $user, array $data): UserProfile
    {
        if (isset($data['date_of_birth'])) {
            $dob = Carbon::parse($data['date_of_birth']);
            if ($dob->age < 18) {
                throw new \InvalidArgumentException('User must be at least 18 years old.');
            }
        }

        $existingProfile = $user->profile;

        // Server-controlled fields: preserved if already present on existing profile
        $gender = $existingProfile?->gender ?? ($user->role?->value ?? 'male');
        $country = $existingProfile?->country ?? ($user->country ?? 'KE');
        $countryCode = $existingProfile?->country_code ?? 'KE';

        $profileData = [
            'display_name' => $data['display_name'] ?? $existingProfile?->display_name ?? $user->name,
            'date_of_birth' => $data['date_of_birth'] ?? $existingProfile?->date_of_birth,
            'gender' => $gender,
            'bio' => $data['bio'] ?? $existingProfile?->bio,
            'city' => $data['city'] ?? $existingProfile?->city,
            'country' => $country,
            'country_code' => $countryCode,
            'profile_visibility' => $data['profile_visibility'] ?? $existingProfile?->profile_visibility ?? true,
        ];

        $profile = UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $profileData
        );

        // Sync interests
        $maxInterests = (int) PlatformSetting::get('max_user_interests', 10);
        if (isset($data['interest_ids']) && is_array($data['interest_ids'])) {
            $validIds = array_slice($data['interest_ids'], 0, $maxInterests);
            $user->interests()->sync($validIds);

            // Update JSON interests column for backward compatibility
            $interestNames = Interest::whereIn('id', $validIds)->pluck('name')->toArray();
            $profile->update(['interests' => $interestNames]);
        } elseif (isset($data['interests']) && is_array($data['interests'])) {
            $names = array_slice($data['interests'], 0, $maxInterests);
            $interestIds = Interest::whereIn('name', $names)->pluck('id')->toArray();
            $user->interests()->sync($interestIds);
            $profile->update(['interests' => $names]);
        }

        return $profile->fresh();
    }
}
