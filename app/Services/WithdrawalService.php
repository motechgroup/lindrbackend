<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WithdrawalService
{
    public function __construct(
        protected CreatorEligibilityService $eligibilityService,
        protected CreditLedgerService $creditLedgerService,
        protected NotificationService $notificationService,
        protected MpesaService $mpesaService
    ) {}

    /**
     * Get available credit balance for a creator.
     */
    public function getAvailableCredits(User $user): int
    {
        $wallet = Wallet::where('user_id', $user->id)->first();

        return (int) ($wallet?->credits ?? 0);
    }

    /**
     * Get available earnings for a creator.
     */
    public function getAvailableEarnings(User $user): int
    {
        return $this->getAvailableCredits($user);
    }

    /**
     * Request a payout withdrawal for a verified creator with atomic row locking, idempotency, and historical rate preservation.
     */
    public function requestWithdrawal(
        User $user,
        int $credits,
        ?string $mpesaNumber = null,
        ?string $idempotencyKey = null
    ): Withdrawal {
        // 1. Idempotency Check: prevent duplicate submissions
        if (! empty($idempotencyKey)) {
            $existing = Withdrawal::where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        // 2. Validate eligibility and limits
        $canWithdraw = $this->eligibilityService->canWithdraw($user, $credits);
        if (! $canWithdraw['allowed']) {
            throw new \InvalidArgumentException($canWithdraw['reason']);
        }

        $payoutPhone = $mpesaNumber ?? $user->mpesa_phone;
        $conversionRate = (float) PlatformSetting::get('credits_per_usd', 10.0);
        $amountUsd = round($credits / max($conversionRate, 0.01), 2);
        $amountKes = round($amountUsd * 130, 2);

        $withdrawal = DB::transaction(function () use ($user, $credits, $payoutPhone, $amountUsd, $amountKes, $conversionRate, $idempotencyKey) {
            $withdrawalId = (string) Str::uuid();

            // 3. Pessimistic wallet row locking & debit
            $this->creditLedgerService->deductCreditsForWithdrawal(
                $user,
                (float) $credits,
                $withdrawalId,
                "Payout withdrawal request of {$credits} credits (\$ {$amountUsd} USD / KSh {$amountKes})"
            );

            // 4. Record withdrawal with preserved conversion rate
            $withdrawal = Withdrawal::create([
                'id' => $withdrawalId,
                'user_id' => $user->id,
                'female_user_id' => $user->id, // Backward compatibility
                'credits_deducted' => $credits,
                'amount_kes' => $amountKes,
                'cash_amount_usd' => $amountUsd,
                'conversion_rate' => $conversionRate,
                'mpesa_number' => $payoutPhone,
                'method' => 'mpesa',
                'idempotency_key' => $idempotencyKey,
                'status' => WithdrawalStatus::Pending,
            ]);

            $this->notificationService->notifyWithdrawalStatus($user, 'pending', $credits);

            return $withdrawal;
        });

        // 5. Dispatch M-Pesa B2C payout request asynchronously / background service
        $payoutResult = $this->mpesaService->sendB2CPayout($withdrawal);

        if ($payoutResult['success']) {
            $withdrawal->update([
                'status' => WithdrawalStatus::Processing,
                'provider_reference' => $payoutResult['conversation_id'] ?? $payoutResult['originator_conversation_id'] ?? $withdrawal->provider_reference,
            ]);
        } else {
            // Immediate B2C request failure (e.g. invalid endpoint or authorization failure)
            $this->processWithdrawal(
                $withdrawal,
                WithdrawalStatus::Failed,
                null,
                'M-Pesa B2C submission failed: '.($payoutResult['response_description'] ?? 'Unknown error'),
                null,
                $payoutResult['response_description'] ?? 'API submission failed'
            );
        }

        return $withdrawal->fresh();
    }

    /**
     * Admin or system action to process, approve, fail, or reverse withdrawal with strict state transitions and auditable refunds.
     */
    public function processWithdrawal(
        Withdrawal $withdrawal,
        WithdrawalStatus $newStatus,
        ?User $adminUser = null,
        ?string $adminNotes = null,
        ?string $providerReference = null,
        ?string $failureReason = null
    ): Withdrawal {
        return DB::transaction(function () use ($withdrawal, $newStatus, $adminUser, $adminNotes, $providerReference, $failureReason) {
            $previousStatus = $withdrawal->status;

            // Enforce state transition safety
            if (! $previousStatus->canTransitionTo($newStatus)) {
                throw new \DomainException("Invalid status transition from {$previousStatus->value} to {$newStatus->value}.");
            }

            $user = User::find($withdrawal->user_id ?? $withdrawal->female_user_id);

            // Check if transitioning to a failed, rejected, cancelled, or reversed state
            $isReversalState = in_array($newStatus, [
                WithdrawalStatus::Failed,
                WithdrawalStatus::Rejected,
                WithdrawalStatus::Cancelled,
                WithdrawalStatus::Reversed,
            ]);

            // Restore credits if moving from an active state to a failed/reversed state
            if ($isReversalState && ! $previousStatus->isFinal()) {
                if ($user && $withdrawal->credits_deducted > 0) {
                    $reason = $failureReason ?? $adminNotes ?? "Withdrawal transition to {$newStatus->value}";
                    $this->creditLedgerService->restoreCreditsForWithdrawal(
                        $user,
                        (float) $withdrawal->credits_deducted,
                        (string) $withdrawal->id,
                        $reason
                    );
                }
            } elseif ($newStatus === WithdrawalStatus::Reversed && in_array($previousStatus, [WithdrawalStatus::Paid, WithdrawalStatus::Success])) {
                // Reversal of an already successful payout
                if ($user && $withdrawal->credits_deducted > 0) {
                    $reason = $failureReason ?? $adminNotes ?? 'Reversal of successful withdrawal payout';
                    $this->creditLedgerService->restoreCreditsForWithdrawal(
                        $user,
                        (float) $withdrawal->credits_deducted,
                        (string) $withdrawal->id,
                        $reason
                    );
                }
            }

            $withdrawal->update([
                'status' => $newStatus,
                'admin_notes' => $adminNotes ?? $withdrawal->admin_notes,
                'failure_reason' => $failureReason ?? $withdrawal->failure_reason,
                'provider_reference' => $providerReference ?? $withdrawal->provider_reference,
                'processed_by_admin_id' => $adminUser?->id ?? $withdrawal->processed_by_admin_id,
                'processed_at' => now(),
            ]);

            if ($user) {
                $statusStr = $newStatus->value;
                $this->notificationService->notifyWithdrawalStatus($user, $statusStr, (int) $withdrawal->credits_deducted);
            }

            return $withdrawal->fresh();
        });
    }
}
