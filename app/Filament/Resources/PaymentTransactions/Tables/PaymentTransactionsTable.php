<?php

namespace App\Filament\Resources\PaymentTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_reference')
                    ->label('Reference')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable(),
                TextColumn::make('provider_code')
                    ->label('Provider')
                    ->badge(),
                TextColumn::make('payment_method_code')
                    ->label('Method')
                    ->badge(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency ?? 'KES')
                    ->sortable(),
                TextColumn::make('expected_coins')
                    ->label('Coins')
                    ->numeric()
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'successful' => 'success',
                        'pending', 'processing' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('provider_reference')
                    ->label('Gateway Ref')
                    ->fontFamily('mono')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
