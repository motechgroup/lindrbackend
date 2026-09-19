<?php

namespace App\Filament\Resources\CoinPackages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoinPackagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('coin_amount')->label('Coins')->sortable(),
                TextColumn::make('bonus_coins')->label('Bonus Coins')->sortable(),
                TextColumn::make('price_kes')->label('Price (KES)')->money('KES')->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active'),
                TextColumn::make('display_order')->sortable(),
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
