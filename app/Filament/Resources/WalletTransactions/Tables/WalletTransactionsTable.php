<?php

namespace App\Filament\Resources\WalletTransactions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state): string => match ((string) ($state?->value ?? $state)) {
                        'CREDIT', 'TOPUP' => 'success',
                        'DEBIT', 'MATCH', 'CALL', 'CHAT', 'GIFT', 'SPOTLIGHT' => 'info',
                        'REFUND' => 'warning',
                        'ADJUSTMENT' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CREDIT' => 'success',
                        'DEBIT' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Tokens')
                    ->numeric()
                    ->weight('bold')
                    ->color(fn ($record) => $record->direction === 'CREDIT' ? 'success' : 'danger')
                    ->sortable(),
                TextColumn::make('balance_before')
                    ->label('Before')
                    ->numeric(),
                TextColumn::make('balance_after')
                    ->label('After')
                    ->numeric()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('status')
                    ->badge()
                    ->color('success'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
