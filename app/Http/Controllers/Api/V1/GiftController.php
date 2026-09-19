<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\GiftResource;
use App\Http\Resources\GiftTransactionResource;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Services\GiftService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    use ApiResponse;

    public function __construct(public GiftService $giftService) {}

    public function index(): JsonResponse
    {
        $gifts = Gift::where('is_active', true)->orderBy('id', 'asc')->get();

        return $this->successResponse(
            GiftResource::collection($gifts),
            'Virtual gifts retrieved.'
        );
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'gift_id' => ['required', 'integer', 'exists:gifts,id'],
        ]);

        $sender = $request->user();
        $recipient = User::find($validated['recipient_id']);
        if (! $recipient) {
            return response()->json([
                'success' => false,
                'message' => 'Recipient not found.',
                'error_code' => 'RECIPIENT_UNAVAILABLE',
            ], 404);
        }

        $gift = Gift::find($validated['gift_id']);
        if (! $gift) {
            return response()->json([
                'success' => false,
                'message' => 'Gift not found.',
                'error_code' => 'GIFT_NOT_FOUND',
            ], 404);
        }

        try {
            $transaction = $this->giftService->sendGift($sender, $recipient, $gift);
            $freshSender = $sender->fresh();
            $tokenBalance = (int) ($freshSender->wallet?->coin_balance ?? 0);

            $txData = (new GiftTransactionResource($transaction->load(['gift', 'sender', 'recipient'])))->resolve();

            return response()->json([
                'success' => true,
                'message' => 'Gift sent successfully!',
                'data' => $txData,
                'gift' => $txData,
                'wallet' => [
                    'token_balance' => $tokenBalance,
                    'balance' => $tokenBalance,
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $errorCode = 'GIFT_FAILED';
            $status = 400;

            if (str_contains($msg, 'INSUFFICIENT_TOKENS')) {
                $errorCode = 'INSUFFICIENT_TOKENS';
                $status = 400;
            } elseif (str_contains($msg, 'SELF_GIFT') || str_contains($msg, 'INVALID_TARGET') || str_contains($msg, 'Self interaction')) {
                $errorCode = 'SELF_GIFT';
                $status = 400;
            } elseif (str_contains($msg, 'RECIPIENT_BLOCKED') || str_contains($msg, 'USER_BLOCKED') || str_contains($msg, 'blocked between')) {
                $errorCode = 'RECIPIENT_BLOCKED';
                $status = 403;
            } elseif (str_contains($msg, 'ACCOUNT_SUSPENDED') || str_contains($msg, 'ACCOUNT_BANNED') || str_contains($msg, 'restricted')) {
                $errorCode = 'ACCOUNT_SUSPENDED';
                $status = 403;
            } elseif (str_contains($msg, 'RECIPIENT_UNAVAILABLE')) {
                $errorCode = 'RECIPIENT_UNAVAILABLE';
                $status = 400;
            } elseif (str_contains($msg, 'GIFT_UNAVAILABLE')) {
                $errorCode = 'GIFT_UNAVAILABLE';
                $status = 400;
            }

            return response()->json([
                'success' => false,
                'message' => preg_replace('/^[A-Z_]+:\s*/', '', $msg),
                'error_code' => $errorCode,
            ], $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'An error occurred while sending the gift.',
                'error_code' => 'SERVER_ERROR',
            ], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $transactions = GiftTransaction::with(['gift', 'sender', 'recipient'])
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)
                    ->orWhere('recipient_id', $user->id);
            })
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            GiftTransactionResource::collection($transactions)->response()->getData(true),
            'Gift history retrieved.'
        );
    }

    public function sent(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $transactions = GiftTransaction::with(['gift', 'sender', 'recipient'])
            ->where('sender_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            GiftTransactionResource::collection($transactions)->response()->getData(true),
            'Sent gifts retrieved.'
        );
    }

    public function received(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $transactions = GiftTransaction::with(['gift', 'sender', 'recipient'])
            ->where('recipient_id', $user->id)
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            GiftTransactionResource::collection($transactions)->response()->getData(true),
            'Received gifts retrieved.'
        );
    }
}
