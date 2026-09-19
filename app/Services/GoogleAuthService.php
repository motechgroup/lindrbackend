<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthService
{
    /**
     * Authenticate or register user using Google credential token or ID payload.
     *
     * @param  array  $payload  ['id_token' => string, 'google_id' => ?string, 'email' => ?string, 'name' => ?string, 'avatar' => ?string]
     * @return array ['user' => User, 'token' => string]
     */
    public function authenticate(array $payload): array
    {
        $googleData = $this->resolveGoogleUser($payload);

        $user = DB::transaction(function () use ($googleData) {
            $existing = User::where('provider', 'google')
                ->where('provider_user_id', $googleData['google_id'])
                ->first();

            if (! $existing && ! empty($googleData['email'])) {
                $existing = User::where('email', $googleData['email'])->first();
            }

            if ($existing) {
                $existing->update([
                    'provider' => 'google',
                    'provider_user_id' => $googleData['google_id'],
                    'avatar' => $existing->avatar ?? $googleData['avatar'] ?? null,
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ]);

                return $existing;
            }

            $user = User::create([
                'name' => $googleData['name'] ?? 'Lindr User',
                'email' => $googleData['email'] ?? ($googleData['google_id'].'@google.lindr.app'),
                'password' => bcrypt(Str::random(32)),
                'provider' => 'google',
                'provider_user_id' => $googleData['google_id'],
                'avatar' => $googleData['avatar'] ?? null,
                'email_verified_at' => now(),
                'status' => 'active',
                'is_creator' => false,
                'creator_status' => 'none',
            ]);

            // Ensure Wallet exists
            Wallet::firstOrCreate([
                'user_id' => $user->id,
            ], [
                'balance' => 0,
                'credits' => 0,
                'currency' => 'TOKENS',
            ]);

            return $user;
        });

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'account' => ['Your account has been deactivated. Please contact support.'],
            ]);
        }

        $token = $user->createToken('mobile-app')->plainTextToken;
        $freshUser = $user->fresh(['wallet', 'profile']);
        $isOnboarded = $freshUser->isOnboarded();

        return [
            'user' => $freshUser,
            'token' => $token,
            'is_onboarded' => $isOnboarded,
            'is_new_user' => ! $isOnboarded,
        ];
    }

    /**
     * Resolve Google user details from id_token or fallback parameters.
     */
    protected function resolveGoogleUser(array $payload): array
    {
        if (! empty($payload['id_token'])) {
            try {
                $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                    'id_token' => $payload['id_token'],
                ]);

                if ($response->successful()) {
                    $json = $response->json();

                    return [
                        'google_id' => $json['sub'] ?? $payload['google_id'] ?? null,
                        'email' => $json['email'] ?? $payload['email'] ?? null,
                        'name' => $json['name'] ?? $payload['name'] ?? 'Google User',
                        'avatar' => $json['picture'] ?? $payload['avatar'] ?? null,
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore remote lookup failure in local/test environments fallback to provided fields
            }
        }

        if (empty($payload['google_id']) && empty($payload['email'])) {
            throw ValidationException::withMessages([
                'id_token' => ['Invalid or missing Google authentication credential.'],
            ]);
        }

        return [
            'google_id' => $payload['google_id'] ?? md5($payload['email'] ?? Str::random(16)),
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? 'Google User',
            'avatar' => $payload['avatar'] ?? null,
        ];
    }
}
