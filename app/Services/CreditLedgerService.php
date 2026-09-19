<?php

namespace App\Services;

use App\Models\CreatorCreditLedger;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditLedgerService
{
    /**
     * Credit a creator with credits from an interaction.
     */
    public function creditCreator(
        User $creator,
        int|float $amountCredits,
        string $sourceType = 'CALL_EARNING',
        ?string $sourceId = null,
        ?string $description = null,
        array $metadata = []
    ): CreatorCreditLedger {
        $normalizedType = match (strtolower($sourceType)) {
            'call', 'call_earning' => 'CALL_EARNING',
            'chat', 'chat_earning' => 'CHAT_EARNING',
            'gift', 'gift_earning' => 'GIFT_EARNING',
            'refund', 'payout_refund' => 'REFUND',
            default => strtoupper($sourceType),
        };

        return $this->addCredits(
            $creator,
            $normalizedType,
            (float) $amountCredits,
            (int) ($metadata['gross_tokens'] ?? 0),
            (int) ($metadata['platform_share_tokens'] ?? 0),
            $description,
            $sourceId,
            $metadata['reference_type'] ?? strtolower($normalizedType)
        );
    }

    /**
     * Add credits to creator ledger and wallet balance with strict idempotency and row locking.
     */
    public function addCredits(
        User $creator,
        string $type,
        float $amountCredits,
        int $grossTokens = 0,
        int $platformShareTokens = 0,
        ?string $description = null,
        ?string $referenceId = null,
        ?string $referenceType = null
    ): CreatorCreditLedger {
        return DB::transaction(function () use (
            $creator, $type, $amountCredits, $grossTokens, $platformShareTokens, $description, $referenceId, $referenceType
        ) {
            $normalizedType = strtoupper($type);

            // 1. Idempotency check: prevent duplicate earnings for the exact same reference
            if (! empty($referenceId) && ! empty($referenceType)) {
                $existing = CreatorCreditLedger::where('user_id', $creator->id)
                    ->where('transaction_type', $normalizedType)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', (string) $referenceId)
                    ->first();

                if ($existing) {
                    Log::info("Idempotent credit skipped: ledger entry already exists for user #{$creator->id}, ref #{$referenceId}");

                    return $existing;
                }
            }

            // 2. Lock wallet row for pessimistic update
            $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create([
                    'user_id' => $creator->id,
                    'balance' => 0,
                    'credits' => 0,
                ]);
                $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            }

            $conversionRate = (float) PlatformSetting::get('credits_per_usd', 10.0);
            $amountUsd = round($amountCredits / max($conversionRate, 0.01), 2);
            $amountKes = round($amountUsd * 130, 2);

            $newBalance = max(0, (float) $wallet->credits + $amountCredits);
            $wallet->credits = $newBalance;
            $wallet->save();

            return CreatorCreditLedger::create([
                'user_id' => $creator->id,
                'transaction_type' => $normalizedType,
                'amount_credits' => $amountCredits,
                'balance_after' => $newBalance,
                'conversion_rate' => $conversionRate,
                'cash_value_usd' => $amountUsd,
                'cash_value_kes' => $amountKes,
                'gross_token_amount' => $grossTokens,
                'platform_share_tokens' => $platformShareTokens,
                'description' => $description,
                'reference_id' => $referenceId ? (string) $referenceId : null,
                'reference_type' => $referenceType,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Debit creator credits for withdrawal processing with pessimistic row locking.
     */
    public function deductCreditsForWithdrawal(
        User $creator,
        float $amountCredits,
        string $withdrawalId,
        ?string $description = null
    ): CreatorCreditLedger {
        return DB::transaction(function () use ($creator, $amountCredits, $withdrawalId, $description) {
            // Idempotency check for withdrawal debit
            $existing = CreatorCreditLedger::where('user_id', $creator->id)
                ->where('transaction_type', 'WITHDRAWAL')
                ->where('reference_type', 'withdrawal')
                ->where('reference_id', (string) $withdrawalId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            if (! $wallet || $wallet->credits < $amountCredits) {
                throw new \InvalidArgumentException('Insufficient withdrawable creator credits balance.');
            }

            $conversionRate = (float) PlatformSetting::get('credits_per_usd', 10.0);
            $amountUsd = round($amountCredits / max($conversionRate, 0.01), 2);
            $amountKes = round($amountUsd * 130, 2);

            $newBalance = max(0, (float) $wallet->credits - $amountCredits);
            $wallet->credits = $newBalance;
            $wallet->save();

            return CreatorCreditLedger::create([
                'user_id' => $creator->id,
                'transaction_type' => 'WITHDRAWAL',
                'amount_credits' => -$amountCredits,
                'balance_after' => $newBalance,
                'conversion_rate' => $conversionRate,
                'cash_value_usd' => $amountUsd,
                'cash_value_kes' => $amountKes,
                'description' => $description ?? "Withdrawal request #{$withdrawalId}",
                'reference_id' => (string) $withdrawalId,
                'reference_type' => 'withdrawal',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Restore creator credits for a failed, cancelled, or reversed withdrawal.
     */
    public function restoreCreditsForWithdrawal(
        User $creator,
        float $amountCredits,
        string $withdrawalId,
        string $reason = 'Withdrawal failed or reversed'
    ): CreatorCreditLedger {
        return DB::transaction(function () use ($creator, $amountCredits, $withdrawalId, $reason) {
            // Idempotency check: ensure we don't refund the same withdrawal twice
            $existing = CreatorCreditLedger::where('user_id', $creator->id)
                ->where('transaction_type', 'WITHDRAWAL_REVERSAL')
                ->where('reference_type', 'withdrawal')
                ->where('reference_id', (string) $withdrawalId)
                ->first();

            if ($existing) {
                return $existing;
            }

            $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create(['user_id' => $creator->id, 'balance' => 0, 'credits' => 0]);
                $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            }

            $conversionRate = (float) PlatformSetting::get('credits_per_usd', 10.0);
            $amountUsd = round($amountCredits / max($conversionRate, 0.01), 2);
            $amountKes = round($amountUsd * 130, 2);

            $newBalance = (float) $wallet->credits + $amountCredits;
            $wallet->credits = $newBalance;
            $wallet->save();

            return CreatorCreditLedger::create([
                'user_id' => $creator->id,
                'transaction_type' => 'WITHDRAWAL_REVERSAL',
                'amount_credits' => $amountCredits,
                'balance_after' => $newBalance,
                'conversion_rate' => $conversionRate,
                'cash_value_usd' => $amountUsd,
                'cash_value_kes' => $amountKes,
                'description' => "Reversal refund for withdrawal #{$withdrawalId}: {$reason}",
                'reference_id' => (string) $withdrawalId,
                'reference_type' => 'withdrawal',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Perform an audited manual credit or debit adjustment on a creator's balance.
     */
    public function addManualAdjustment(
        User $creator,
        float $amountCredits,
        string $reason,
        ?User $adminUser = null
    ): CreatorCreditLedger {
        return DB::transaction(function () use ($creator, $amountCredits, $reason, $adminUser) {
            $type = $amountCredits >= 0 ? 'MANUAL_CREDIT' : 'MANUAL_DEBIT';

            $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create(['user_id' => $creator->id, 'balance' => 0, 'credits' => 0]);
                $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            }

            if ($amountCredits < 0 && (float) $wallet->credits < abs($amountCredits)) {
                throw new \InvalidArgumentException('Insufficient creator credit balance for manual debit.');
            }

            $conversionRate = (float) PlatformSetting::get('credits_per_usd', 10.0);
            $amountUsd = round($amountCredits / max($conversionRate, 0.01), 2);
            $amountKes = round($amountUsd * 130, 2);

            $newBalance = max(0, (float) $wallet->credits + $amountCredits);
            $wallet->credits = $newBalance;
            $wallet->save();

            $adminInfo = $adminUser ? "Admin #{$adminUser->id} ({$adminUser->name})" : 'System Admin';

            return CreatorCreditLedger::create([
                'user_id' => $creator->id,
                'transaction_type' => $type,
                'amount_credits' => $amountCredits,
                'balance_after' => $newBalance,
                'conversion_rate' => $conversionRate,
                'cash_value_usd' => $amountUsd,
                'cash_value_kes' => $amountKes,
                'description' => "Manual adjustment by {$adminInfo}: {$reason}",
                'reference_id' => $adminUser ? (string) $adminUser->id : 'system',
                'reference_type' => 'admin_adjustment',
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Reconcile creator wallet credits with sum of ledger entries.
     *
     * @return array{ledger_sum: float, wallet_balance: float, discrepancy: float, reconciled: bool}
     */
    public function reconcileBalance(User $creator): array
    {
        return DB::transaction(function () use ($creator) {
            $ledgerSum = (float) CreatorCreditLedger::where('user_id', $creator->id)->sum('amount_credits');

            $wallet = Wallet::where('user_id', $creator->id)->lockForUpdate()->first();
            $walletBalance = (float) ($wallet?->credits ?? 0);

            $discrepancy = round($ledgerSum - $walletBalance, 2);
            $reconciled = false;

            if (abs($discrepancy) > 0.01) {
                if ($wallet) {
                    $wallet->credits = max(0, $ledgerSum);
                    $wallet->save();
                    $reconciled = true;
                    Log::warning("Reconciled creator #{$creator->id} credits balance from {$walletBalance} to {$ledgerSum}");
                }
            }

            return [
                'ledger_sum' => $ledgerSum,
                'wallet_balance' => $walletBalance,
                'discrepancy' => $discrepancy,
                'reconciled' => $reconciled,
            ];
        });
    }
}
