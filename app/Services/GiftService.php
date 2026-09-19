<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Gift;
use App\Models\GiftTransaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class GiftService
{
    public function __construct(
        public WalletService $walletService,
        public ChatService $chatService,
        public MonetizationService $monetizationService,
        public CreditLedgerService $creditLedgerService,
        public SafetyService $safetyService,
        public NotificationService $notificationService
    ) {}

    /**
     * Send a gift from sender to recipient.
     */
    public function sendGift(User $sender, User $recipient, Gift $gift): GiftTransaction
    {
        $safetyCheck = $this->safetyService->canSendGift($sender, $recipient);
        if (! $safetyCheck['allowed']) {
            $code = $safetyCheck['error_code'] ?? 'RECIPIENT_UNAVAILABLE';
            throw new \InvalidArgumentException("{$code}: {$safetyCheck['reason']}");
        }

        if (! $gift->is_active) {
            throw new \InvalidArgumentException('GIFT_UNAVAILABLE: This gift is currently unavailable.');
        }

        return DB::transaction(function () use ($sender, $recipient, $gift) {
            // Lock sender wallet & check balance atomically
            $wallet = Wallet::where('user_id', $sender->id)->lockForUpdate()->first();
            if (! $wallet || $wallet->coin_balance < $gift->coin_price) {
                throw new \InvalidArgumentException('INSUFFICIENT_TOKENS: You do not have enough tokens for this gift.');
            }

            // Debit tokens from sender wallet
            $walletTx = $this->walletService->debitCoins(
                $sender,
                $gift->coin_price,
                TransactionType::Debit,
                Gift::class,
                (string) $gift->id,
                "Sent gift: {$gift->name}"
            );

            // Calculate revenue split
            $gender = strtolower($recipient->profile?->gender ?? 'female');
            $split = $this->monetizationService->calculateSplit($gift->coin_price, 'gift', $gender);
            $recipientShareCoins = $split['creator_amount'];
            $platformShareCoins = $split['platform_amount'];

            $giftTx = GiftTransaction::create([
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'gift_id' => $gift->id,
                'coin_price' => $gift->coin_price,
                'platform_share' => $platformShareCoins,
                'recipient_share' => $recipientShareCoins,
                'recipient_earnings_amount' => $recipientShareCoins,
                'status' => 'completed',
            ]);

            $walletTx->update(['reference_id' => (string) $giftTx->id]);

            // If recipient is a verified Creator, credit creator credits
            if ($recipient->isCreatorVerified() && $recipientShareCoins > 0) {
                $this->creditLedgerService->creditCreator(
                    $recipient,
                    $recipientShareCoins,
                    'gift',
                    (string) $giftTx->id,
                    "Gift received: {$gift->name}",
                    [
                        'sender_id' => $sender->id,
                        'gift_id' => $gift->id,
                        'gift_name' => $gift->name,
                        'gross_tokens' => $gift->coin_price,
                        'creator_share_pct' => $split['creator_share_pct'],
                    ]
                );
            }

            // Trigger GIFT_RECEIVED notification after successful transaction
            $this->notificationService->notifyGiftReceived($recipient, $sender, $gift->name, $recipientShareCoins);

            // Post gift message into conversation if available
            try {
                $conversation = $this->chatService->getOrCreateConversation($sender, $recipient);
                $this->chatService->sendMessage(
                    $sender,
                    $conversation,
                    "🎁 Sent a gift: {$gift->name}",
                    'gift',
                    true
                );
            } catch (\Exception $e) {
                // Non-blocking if conversation creation fails
            }

            return $giftTx;
        });
    }
}
