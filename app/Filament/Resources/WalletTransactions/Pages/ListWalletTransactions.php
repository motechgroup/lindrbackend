<?php

namespace App\Filament\Resources\WalletTransactions\Pages;

use App\Enums\TransactionType;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use App\Models\User;
use App\Services\WalletService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListWalletTransactions extends ListRecords
{
    protected static string $resource = WalletTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual_adjustment')
                ->label('Manual Adjustment')
                ->color('warning')
                ->icon('heroicon-o-adjustments-horizontal')
                ->form([
                    Select::make('user_id')
                        ->label('Select User')
                        ->options(User::query()->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Select::make('direction')
                        ->label('Adjustment Direction')
                        ->options([
                            'CREDIT' => 'Credit (+) Tokens',
                            'DEBIT' => 'Debit (-) Tokens',
                        ])
                        ->required(),
                    TextInput::make('amount')
                        ->label('Token Amount')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Reason for Adjustment')
                        ->required(),
                ])
                ->action(function (array $data, WalletService $walletService): void {
                    $user = User::findOrFail($data['user_id']);
                    $amount = (int) $data['amount'];
                    $reason = $data['reason'];
                    $adminId = Auth::id();

                    $idempotencyKey = 'ADM_ADJ_'.now()->timestamp.'_'.rand(1000, 9999);
                    $metadata = [
                        'adjusted_by_admin_id' => $adminId,
                        'reason' => $reason,
                    ];

                    try {
                        if ($data['direction'] === 'CREDIT') {
                            $walletService->creditCoins(
                                $user,
                                $amount,
                                TransactionType::Adjustment,
                                'AdminAdjustment',
                                (string) $adminId,
                                "Admin Adjustment (Credit): {$reason}",
                                $idempotencyKey,
                                $metadata
                            );
                        } else {
                            $walletService->debitCoins(
                                $user,
                                $amount,
                                TransactionType::Adjustment,
                                'AdminAdjustment',
                                (string) $adminId,
                                "Admin Adjustment (Debit): {$reason}",
                                $idempotencyKey,
                                $metadata
                            );
                        }

                        Notification::make()
                            ->title('Token Adjustment Successful')
                            ->body("Adjusted {$user->name}'s wallet balance by {$data['direction']} {$amount} tokens.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Adjustment Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
