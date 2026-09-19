<?php

namespace App\Filament\Resources\CoinPurchases\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoinPurchasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Purchase ID')->searchable(),
                TextColumn::make('user.name')->label('User')->searchable()->sortable(),
                TextColumn::make('package.name')->label('Package')->sortable(),
                TextColumn::make('amount_kes')->label('Amount (KES)')->money('KES')->sortable(),
                TextColumn::make('coins_credited')->label('Coins Credited')->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('phone_number')->searchable(),
                TextColumn::make('mpesa_receipt_number')->label('M-Pesa Receipt')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
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
