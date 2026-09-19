<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LivenessVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LivenessController extends Controller
{
    /**
     * Issue dynamic randomized liveness pose challenge for client selfie verification.
     *
     * GET /api/v1/liveness/challenge
     */
    public function challenge(): JsonResponse
    {
        $gestures = ['turn_head_left', 'turn_head_right', 'smile', 'blink', 'nod_head'];
        shuffle($gestures);
        $selected = array_slice($gestures, 0, 3);

        $challengeId = 'ch_'.Str::random(16);

        return response()->json([
            'success' => true,
            'data' => [
                'challenge_id' => $challengeId,
                'poses' => $selected,
                'expires_in_seconds' => 300,
            ],
        ]);
    }

    /**
     * Submit selfie liveness verification photo and gesture proof.
     *
     * POST /api/v1/liveness/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'challenge_id' => 'required|string',
            'selfie_image' => 'required|image|max:10240', // Max 10MB
            'gestures_completed' => 'nullable|array',
            'gestures_completed.*' => 'string',
        ]);

        $user = $request->user();

        // Store private selfie verification image
        $path = $request->file('selfie_image')->store('liveness_verifications', 'private');

        $verification = LivenessVerification::create([
            'user_id' => $user->id,
            'challenge_id' => $validated['challenge_id'],
            'challenge_sequence' => $validated['gestures_completed'] ?? ['turn_head_left', 'smile'],
            'selfie_path' => $path,
            'gestures_completed' => $validated['gestures_completed'] ?? [],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        // Update user status to pending verification
        $user->update([
            'creator_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Liveness verification selfie submitted successfully and is pending admin review.',
            'data' => [
                'verification_id' => $verification->id,
                'status' => 'pending',
            ],
        ]);
    }
}
