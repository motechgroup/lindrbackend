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
            'selfie_image' => 'nullable|file|max:51200', // Max 50MB (image or video)
            'video' => 'nullable|file|max:51200',
            'liveness_video' => 'nullable|file|max:51200',
            'gestures_completed' => 'nullable|array',
            'gestures_completed.*' => 'string',
        ]);

        $user = $request->user();

        $file = $request->file('video') ?? $request->file('liveness_video') ?? $request->file('selfie_image');
        if (! $file) {
            return response()->json([
                'success' => false,
                'message' => 'Liveness verification video or image evidence is required.',
            ], 422);
        }

        // Store private liveness verification evidence
        $path = $file->store('liveness_verifications', 'private');

        $verification = LivenessVerification::create([
            'user_id' => $user->id,
            'challenge_id' => $validated['challenge_id'],
            'challenge_sequence' => $validated['gestures_completed'] ?? ['turn_head_left', 'turn_head_right', 'nod_head', 'open_mouth', 'blink'],
            'selfie_path' => $path,
            'gestures_completed' => $validated['gestures_completed'] ?? ['turn_head_left', 'turn_head_right', 'nod_head', 'open_mouth', 'blink'],
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
