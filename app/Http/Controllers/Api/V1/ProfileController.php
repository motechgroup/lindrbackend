<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserProfileResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(public ProfileService $profileService) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load(['profile', 'photos', 'wallet']);

        return $this->successResponse(new UserResource($user), 'Profile retrieved successfully.');
    }

    public function showUser(User $user): JsonResponse
    {
        $user->load(['profile', 'photos']);

        return $this->successResponse(new UserResource($user), 'User profile retrieved successfully.');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profileService->updateOrCreateProfile($user, $request->validated());

        return $this->successResponse(new UserProfileResource($profile), 'Profile updated successfully.');
    }
}
