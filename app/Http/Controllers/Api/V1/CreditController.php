<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WithdrawalStatus;
use App\Http\Controllers\Controller;
use App\Models\CreatorCreditLedger;
use App\Models\PlatformSetting;
use App\Models\Withdrawal;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    use ApiResponse;

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $user->wallet;
        $isCreator = $user->isCreatorVerified() || (bool) $user->is_creator;

        $availableCredits = (int) ($wallet?->credits ?? 0);
        $minWithdrawalCredits = (int) PlatformSetting::get('minimum_withdrawal_credits', PlatformSetting::get('min_withdrawal_credits', 100));
        $creditsPerUsd = (float) PlatformSetting::get('credits_per_usd', 10.0);

        if (! $isCreator) {
            return $this->successResponse([
                'available_credits' => 0,
                'calls_credits' => 0,
                'chats_credits' => 0,
                'gifts_credits' => 0,
                'total_earned_credits' => 0,
                'total_withdrawn_credits' => 0,
                'credits_per_usd' => $creditsPerUsd,
                'kes_per_usd' => 130.0,
                'min_withdrawal_credits' => $minWithdrawalCredits,
                'minimum_withdrawal_credits' => $minWithdrawalCredits,
                'is_creator' => false,
                'period_earnings' => [
                    'today' => 0,
                    'this_week' => 0,
                    'this_month' => 0,
                    'total' => 0,
                ],
            ], 'Credit summary retrieved.');
        }

        // Aggregate by type
        $callsCredits = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->whereIn('transaction_type', ['CALL_EARNING', 'call'])
            ->sum('amount_credits');

        $chatsCredits = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->whereIn('transaction_type', ['CHAT_EARNING', 'chat'])
            ->sum('amount_credits');

        $giftsCredits = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->whereIn('transaction_type', ['GIFT_EARNING', 'gift'])
            ->sum('amount_credits');

        $totalEarned = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->sum('amount_credits');

        $totalWithdrawn = (int) abs((float) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '<', 0)
            ->where('transaction_type', 'WITHDRAWAL')
            ->sum('amount_credits'));

        $pendingWithdrawalsCredits = (int) Withdrawal::where(function ($q) use ($user) {
            $q->where('user_id', $user->id)->orWhere('female_user_id', $user->id);
        })->whereIn('status', ['pending', 'processing', WithdrawalStatus::Pending, WithdrawalStatus::Processing])
            ->sum('credits_deducted');

        // Period Earnings Aggregation
        $todayEarned = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->where('created_at', '>=', Carbon::today())
            ->sum('amount_credits');

        $weekEarned = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->where('created_at', '>=', Carbon::now()->startOfWeek())
            ->sum('amount_credits');

        $monthEarned = (int) CreatorCreditLedger::where('user_id', $user->id)
            ->where('amount_credits', '>', 0)
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->sum('amount_credits');

        return $this->successResponse([
            'available_credits' => $availableCredits,
            'calls_credits' => $callsCredits,
            'chats_credits' => $chatsCredits,
            'gifts_credits' => $giftsCredits,
            'total_earned_credits' => $totalEarned,
            'total_withdrawn_credits' => $totalWithdrawn,
            'pending_withdrawals_credits' => $pendingWithdrawalsCredits,
            'credits_per_usd' => $creditsPerUsd,
            'kes_per_usd' => 130.0,
            'min_withdrawal_credits' => $minWithdrawalCredits,
            'minimum_withdrawal_credits' => $minWithdrawalCredits,
            'is_creator' => true,
            'period_earnings' => [
                'today' => $todayEarned,
                'this_week' => $weekEarned,
                'this_month' => $monthEarned,
                'total' => $totalEarned,
            ],
        ], 'Credit summary retrieved.');
    }

    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);
        $category = strtolower((string) $request->input('category', 'all'));
        $timeframe = strtolower((string) $request->input('timeframe', 'all'));

        $query = CreatorCreditLedger::where('user_id', $user->id);

        // Filter by Category
        if ($category === 'calls') {
            $query->whereIn('transaction_type', ['CALL_EARNING', 'call']);
        } elseif ($category === 'chats') {
            $query->whereIn('transaction_type', ['CHAT_EARNING', 'chat']);
        } elseif ($category === 'gifts') {
            $query->whereIn('transaction_type', ['GIFT_EARNING', 'gift']);
        } elseif ($category === 'withdrawals') {
            $query->whereIn('transaction_type', ['WITHDRAWAL', 'WITHDRAWAL_REVERSAL']);
        }

        // Filter by Timeframe
        if ($timeframe === 'today') {
            $query->where('created_at', '>=', Carbon::today());
        } elseif ($timeframe === 'week') {
            $query->where('created_at', '>=', Carbon::now()->startOfWeek());
        } elseif ($timeframe === 'month') {
            $query->where('created_at', '>=', Carbon::now()->startOfMonth());
        }

        $transactions = $query->latest('created_at')->paginate($perPage);

        $transformed = $transactions->getCollection()->map(function ($tx) {
            $rawType = strtoupper($tx->transaction_type ?? 'CREDIT');
            $sourceLabel = match ($rawType) {
                'CALL', 'CALL_EARNING' => 'CALL',
                'CHAT', 'CHAT_EARNING' => 'CHAT',
                'GIFT', 'GIFT_EARNING' => 'GIFT',
                'WITHDRAWAL' => 'WITHDRAWAL',
                'WITHDRAWAL_REVERSAL', 'REFUND' => 'REVERSAL',
                default => $rawType,
            };

            return [
                'id' => (string) $tx->id,
                'source' => $sourceLabel,
                'transaction_type' => $rawType,
                'amount' => (int) $tx->amount_credits,
                'amount_credits' => (int) $tx->amount_credits,
                'balance_after' => (int) $tx->balance_after,
                'conversion_rate' => (float) ($tx->conversion_rate ?? 10.0),
                'cash_value_usd' => (float) ($tx->cash_value_usd ?? 0.0),
                'cash_value_kes' => (float) ($tx->cash_value_kes ?? 0.0),
                'description' => $tx->description ?? 'Credit transaction',
                'reference_id' => $tx->reference_id,
                'reference_type' => $tx->reference_type,
                'created_at' => $tx->created_at?->toIso8601String(),
                'formatted_date' => $tx->created_at?->format('M j, Y • g:i A'),
            ];
        });

        $transactions->setCollection($transformed);

        return $this->successResponse($transactions, 'Credit transactions retrieved.');
    }
}
