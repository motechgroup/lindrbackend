<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use ApiResponse;

    /**
     * Store or update user device push token.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:500'],
            'platform' => ['nullable', 'string', 'in:ios,android,web'],
        ]);

        $device = UserDevice::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'push_token' => $validated['push_token'],
            ],
            [
                'platform' => strtolower($validated['platform'] ?? 'android'),
                'active' => true,
                'last_seen_at' => now(),
            ]
        );

        return $this->successResponse($device, 'Device push token registered successfully.', 201);
    }

    /**
     * Deactivate or remove user device push token on logout / account switch.
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string'],
        ]);

        UserDevice::where('user_id', $request->user()->id)
            ->where('push_token', $validated['push_token'])
            ->delete();

        return $this->successResponse(null, 'Device push token deactivated successfully.');
    }
}
