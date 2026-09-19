<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ModerationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use ApiResponse;

    public function __construct(public ModerationService $moderationService) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reported_id' => ['required', 'integer', 'exists:users,id'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $result = $this->moderationService->submitReport(
                $request->user(),
                (int) $validated['reported_id'],
                $validated['category'],
                $validated['description'] ?? null
            );

            return $this->successResponse(
                $result['report'],
                $result['message'],
                201
            );
        } catch (\InvalidArgumentException $e) {
            $code = str_contains($e->getMessage(), 'yourself') ? 'SELF_REPORT' : 'INVALID_REPORT_TARGET';

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $e->getMessage(),
                ],
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'REPORT_RATE_LIMITED',
                    'message' => $e->getMessage(),
                ],
            ], 429);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'SERVER_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
