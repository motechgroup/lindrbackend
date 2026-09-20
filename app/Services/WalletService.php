<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

class WalletService
{
    /**
     * Get or create a wallet for a user.
     */
    public function getWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['coin_balance' => 0]
        );
    }

    /**
     * Credit coins to a user's wallet atomically.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function creditCoins(
        User $user,
        int $amount,
        TransactionType $type = TransactionType::Credit,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be greater than zero.');
        }

        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $amount, $type, $referenceType, $referenceId, $description, $idempotencyKey, $metadata) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet) {
                $wallet = Wallet::create(['user_id' => $user->id, 'coin_balance' => 0]);
            }

            $before = $wallet->coin_balance;
            $after = $before + $amount;

            $wallet->update(['coin_balance' => $after]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description ?? 'Credit coins',
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debit coins from a user's wallet atomically.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function debitCoins(
        User $user,
        int $amount,
        TransactionType $type = TransactionType::Debit,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $description = null,
        ?string $idempotencyKey = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Debit amount must be greater than zero.');
        }

        if ($idempotencyKey) {
            $existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        return DB::transaction(function () use ($user, $amount, $type, $referenceType, $referenceId, $description, $idempotencyKey, $metadata) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            if (! $wallet || $wallet->coin_balance < $amount) {
                throw new \InvalidArgumentException('Insufficient coin balance.');
            }

            $before = $wallet->coin_balance;
            $after = $before - $amount;

            $wallet->update(['coin_balance' => $after]);

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'description' => $description ?? 'Debit coins',
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Get transaction history for a user.
     */
    public function getTransactionHistory(User $user, int $perPage = 20)
    {
        return WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get detailed wallet summary with recent transaction history.
     *
     * @return array<string, mixed>
     */
    public function getWalletSummary(User $user): array
    {
        $wallet = $this->getWallet($user);
        $recent = WalletTransaction::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $chatCoins = (int) PlatformSetting::get('message_cost', PlatformSetting::get('chat_coins', 5));
        $matchCoins = (int) PlatformSetting::get('matching_token_cost', PlatformSetting::get('match_coins', 50));
        $videoCallRate = (int) PlatformSetting::get('video_call_rate_per_minute', 30);
        $audioCallRate = (int) PlatformSetting::get('audio_call_rate_per_minute', 20);

        return [
            'id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'balance' => $wallet->coin_balance,
            'available_balance' => $wallet->coin_balance,
            'coin_balance' => $wallet->coin_balance,
            'currency' => 'TOKENS',
            'rates' => [
                'chat_coins' => $chatCoins,
                'message_cost' => $chatCoins,
                'match_coins' => $matchCoins,
                'matching_token_cost' => $matchCoins,
                'video_call_rate_per_minute' => $videoCallRate,
                'audio_call_rate_per_minute' => $audioCallRate,
            ],
            'recent_transactions' => $recent->map(function ($tx) {
                return [
                    'id' => $tx->id,
                    'transaction_type' => $tx->transaction_type?->value ?? (string) $tx->transaction_type,
                    'direction' => $tx->direction,
                    'amount' => $tx->amount,
                    'balance_before' => $tx->balance_before,
                    'balance_after' => $tx->balance_after,
                    'reference_type' => $tx->reference_type,
                    'reference_id' => $tx->reference_id,
                    'description' => $tx->description,
                    'status' => $tx->status,
                    'created_at' => $tx->created_at?->toIso8601String(),
                ];
            }),
            'updated_at' => $wallet->updated_at?->toIso8601String(),
        ];
    }
}
