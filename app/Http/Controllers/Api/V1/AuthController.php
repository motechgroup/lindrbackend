<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'phone' => $validated['phone'] ?? null,
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'status' => UserStatus::Active,
        ]);

        // Automatically create wallet for user
        $user->wallet()->create([
            'coin_balance' => 0,
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['profile', 'photos', 'wallet']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::where('email', strtolower($validated['login']))
            ->orWhere('phone', $validated['login'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials.', 401, [
                'login' => ['These credentials do not match our records.'],
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            return $this->errorResponse('Your account has been suspended.', 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['profile', 'photos', 'wallet']);

        return $this->successResponse([
            'user' => new UserResource($user),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'photos', 'wallet']);

        return $this->successResponse(new UserResource($user), 'User profile fetched.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return $this->successResponse(null, 'Successfully logged out.');
    }
}
