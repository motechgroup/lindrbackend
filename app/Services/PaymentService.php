<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\CoinPackage;
use App\Models\CoinPurchase;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhook;
use App\Models\User;
use App\Services\Payments\PaymentRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        public WalletService $walletService,
        public PaymentRouter $router
    ) {}

    /**
     * Initiate coin purchase using multi-gateway router.
     */
    public function initiatePurchase(User $user, CoinPackage $package, string $paymentMethod, array $params = []): PaymentTransaction
    {
        return $this->router->initiate($user, $package, $paymentMethod, $params);
    }

    /**
     * Process provider webhook idempotently and credit coins.
     */
    public function processWebhook(string $providerCode, array $payload, array $headers = []): bool
    {
        $webhook = PaymentWebhook::create([
            'provider_code' => $providerCode,
            'event_type' => $payload['event'] ?? $payload['Body']['stkCallback']['ResultDesc'] ?? null,
            'payload' => $payload,
            'processed' => false,
        ]);

        try {
            $provider = $this->router->getProvider($providerCode);
            $parsed = $provider->parseWebhook($payload, $headers);

            if (! $parsed->isValid) {
                Log::warning("Invalid webhook signature for provider {$providerCode}", $headers);
                $webhook->update(['error_message' => 'Invalid webhook signature']);

                return false;
            }

            // Find payment transaction by provider reference or public reference
            $transaction = null;
            if (! empty($parsed->transactionReference)) {
                $transaction = PaymentTransaction::where('public_reference', $parsed->transactionReference)->first();
            }
            if (! $transaction && ! empty($parsed->providerReference)) {
                $transaction = PaymentTransaction::where('provider_reference', $parsed->providerReference)
                    ->orWhere('id', $parsed->providerReference)
                    ->first();
            }

            // Fallback check for legacy coin_purchases checkout_request_id if M-Pesa callback
            if (! $transaction && $providerCode === 'mpesa' && ! empty($parsed->providerReference)) {
                $legacyPurchase = CoinPurchase::where('checkout_request_id', $parsed->providerReference)->first();
                if ($legacyPurchase) {
                    return $this->handleLegacyMpesaCallback($payload);
                }
            }

            if (! $transaction) {
                Log::error("PaymentTransaction not found for webhook provider {$providerCode}", [
                    'parsed' => (array) $parsed,
                ]);
                $webhook->update(['error_message' => 'PaymentTransaction not found']);

                return false;
            }

            $success = $this->applyTransactionStatus($transaction, $parsed->status, $parsed->receiptNumber);

            $webhook->update([
                'processed' => true,
                'processed_at' => now(),
            ]);

            return $success;
        } catch (\Throwable $e) {
            Log::error("Webhook processing error for {$providerCode}: ".$e->getMessage());
            $webhook->update(['error_message' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Atomically transition transaction status and credit coins exactly once.
     */
    public function applyTransactionStatus(PaymentTransaction $transaction, string $status, ?string $receiptNumber = null): bool
    {
        return DB::transaction(function () use ($transaction, $status, $receiptNumber) {
            // Lock record for update to prevent race conditions
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $lockedTx) {
                return false;
            }

            // Idempotency: If already successful, do not re-credit
            if ($lockedTx->status === 'successful') {
                return true;
            }

            if ($status === 'successful') {
                $lockedTx->update([
                    'status' => 'successful',
                    'provider_reference' => $receiptNumber ?? $lockedTx->provider_reference,
                ]);

                // Credit user wallet atomically
                $this->walletService->creditCoins(
                    $lockedTx->user,
                    $lockedTx->expected_coins,
                    TransactionType::Credit,
                    PaymentTransaction::class,
                    (string) $lockedTx->id,
                    "Purchased {$lockedTx->package->name} coin package via {$lockedTx->payment_method_code}",
                    $lockedTx->public_reference
                );

                return true;
            }

            $lockedTx->update([
                'status' => $status,
                'error_message' => $status === 'failed' ? 'Payment declined or failed at gateway.' : null,
            ]);

            return false;
        });
    }

    /**
     * Atomically reverse/refund a successful payment transaction.
     */
    public function reversePayment(PaymentTransaction $transaction, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($transaction, $reason) {
            /** @var PaymentTransaction $lockedTx */
            $lockedTx = PaymentTransaction::where('id', $transaction->id)->lockForUpdate()->first();

            if (! $lockedTx || $lockedTx->status !== 'successful') {
                return false;
            }

            $lockedTx->update([
                'status' => 'reversed',
                'error_message' => $reason ?? 'Payment reversed / refunded.',
            ]);

            $idempotencyKey = 'REV_'.$lockedTx->public_reference;

            // Create compensating debit transaction safely
            $this->walletService->debitCoins(
                $lockedTx->user,
                $lockedTx->expected_coins,
                TransactionType::Refund,
                PaymentTransaction::class,
                (string) $lockedTx->id,
                "Payment reversal for {$lockedTx->public_reference}".($reason ? ": {$reason}" : ''),
                $idempotencyKey,
                ['reversal_reason' => $reason]
            );

            return true;
        });
    }

    /**
     * Backwards-compatible callback handler for M-Pesa.
     */
    public function handleCallback(array $payload): bool
    {
        return $this->processWebhook('mpesa', $payload);
    }

    /**
     * Legacy M-Pesa callback handling.
     */
    protected function handleLegacyMpesaCallback(array $payload): bool
    {
        $provider = $this->router->getProvider('mpesa');
        $parsed = $provider->parseWebhook($payload);
        $checkoutRequestId = $parsed->providerReference;

        $purchase = CoinPurchase::where('checkout_request_id', $checkoutRequestId)->first();
        if (! $purchase || $purchase->status === 'successful') {
            return (bool) $purchase;
        }

        return DB::transaction(function () use ($purchase, $parsed) {
            if ($parsed->status === 'successful') {
                $purchase->update([
                    'status' => 'successful',
                    'mpesa_receipt_number' => $parsed->receiptNumber,
                ]);

                $this->walletService->creditCoins(
                    $purchase->user,
                    $purchase->coins_credited,
                    TransactionType::Credit,
                    CoinPurchase::class,
                    (string) $purchase->id,
                    "Purchased {$purchase->package->name} coin package",
                    (string) $purchase->id
                );

                return true;
            }

            $purchase->update(['status' => 'failed']);

            return false;
        });
    }
}
