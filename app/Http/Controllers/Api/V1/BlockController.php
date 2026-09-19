<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\BlockService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockController extends Controller
{
    use ApiResponse;

    public function __construct(public BlockService $blockService) {}

    public function index(Request $request): JsonResponse
    {
        $blockedUsers = UserBlock::where('blocker_id', $request->user()->id)
            ->with('blocked.profile')
            ->get();

        return $this->successResponse($blockedUsers, 'Blocked users list retrieved.');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'blocked_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($request->user()->id === (int) $validated['blocked_id']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SELF_BLOCK',
                    'message' => 'You cannot block yourself.',
                ],
            ], 422);
        }

        $blockedUser = User::findOrFail($validated['blocked_id']);
        $block = $this->blockService->blockUser($request->user(), $blockedUser);

        return $this->successResponse($block, 'User blocked successfully.', 201);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->blockService->unblockUser($request->user(), $user);

        return $this->successResponse(null, 'User unblocked successfully.');
    }
}
