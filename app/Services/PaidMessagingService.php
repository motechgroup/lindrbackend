<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaidMessagingService
{
    public function __construct(
        public ChatService $chatService,
        public WalletService $walletService,
        public MonetizationService $monetizationService,
        public CreditLedgerService $creditLedgerService
    ) {}

    /**
     * Process paid messaging flow between sender and recipient.
     */
    public function sendPaidMessage(
        User $sender,
        Conversation $conversation,
        string $content,
        string $type = 'text'
    ): Message {
        $costTokens = (int) PlatformSetting::get('message_cost', 5);

        if (! $conversation->isParticipant($sender->id)) {
            throw new AccessDeniedHttpException('Sender is not a participant in this conversation.');
        }

        $recipient = $conversation->getOtherParticipant($sender->id);
        if (! $recipient) {
            throw new \InvalidArgumentException('Recipient participant not found.');
        }

        // Only charge paid message fee if recipient is a verified Creator and sender is not charging themselves
        $shouldCharge = $recipient->isCreatorVerified() && $sender->id !== $recipient->id;

        if (! $shouldCharge) {
            return $this->chatService->sendMessage($sender, $conversation, $content, $type, false);
        }

        return DB::transaction(function () use ($sender, $recipient, $conversation, $content, $type, $costTokens) {
            // Debit tokens from sender wallet
            $walletTx = $this->walletService->debitCoins(
                $sender,
                $costTokens,
                TransactionType::Debit,
                Message::class,
                null,
                'Paid message fee'
            );

            // Persist paid message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'content' => $content,
                'type' => $type,
                'is_paid' => true,
                'is_read' => false,
            ]);

            $walletTx->update(['reference_id' => (string) $message->id]);

            // Calculate split for creator
            $gender = strtolower($recipient->profile?->gender ?? 'female');
            $split = $this->monetizationService->calculateSplit($costTokens, 'chat', $gender);
            $creatorCreditAmount = $split['creator_amount'];

            if ($creatorCreditAmount > 0) {
                $this->creditLedgerService->creditCreator(
                    $recipient,
                    $creatorCreditAmount,
                    'chat',
                    (string) $message->id,
                    "Earnings from paid chat message from {$sender->name}",
                    [
                        'sender_id' => $sender->id,
                        'conversation_id' => $conversation->id,
                        'gross_tokens' => $costTokens,
                        'creator_share_pct' => $split['creator_share_pct'],
                    ]
                );
            }

            $conversation->touch();

            return $message;
        });
    }
}
