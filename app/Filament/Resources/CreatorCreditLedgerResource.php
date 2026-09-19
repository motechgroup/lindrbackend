<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CreatorCreditLedgerResource\Pages\ListCreatorCreditLedgers;
use App\Models\CreatorCreditLedger;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CreatorCreditLedgerResource extends Resource
{
    protected static ?string $model = CreatorCreditLedger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static UnitEnum|string|null $navigationGroup = 'Financials';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('user.name')->label('Creator')->searchable()->sortable(),
                TextColumn::make('transaction_type')->label('Type')->badge()->sortable(),
                TextColumn::make('amount_credits')->label('Credits')->sortable(),
                TextColumn::make('balance_after')->label('Balance After')->sortable(),
                TextColumn::make('conversion_rate')->label('Rate')->sortable(),
                TextColumn::make('cash_value_kes')->label('Value (KES)')->money('KES')->sortable(),
                TextColumn::make('description')->limit(30),
                TextColumn::make('reference_id')->label('Ref ID')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('transaction_type')
                    ->options([
                        'CALL_EARNING' => 'Call Earning',
                        'CHAT_EARNING' => 'Chat Earning',
                        'GIFT_EARNING' => 'Gift Earning',
                        'WITHDRAWAL' => 'Withdrawal',
                        'WITHDRAWAL_REVERSAL' => 'Withdrawal Reversal',
                        'MANUAL_CREDIT' => 'Manual Credit',
                        'MANUAL_DEBIT' => 'Manual Debit',
                        'ADJUSTMENT' => 'Adjustment',
                        'REFUND' => 'Refund',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreatorCreditLedgers::route('/'),
        ];
    }
}
