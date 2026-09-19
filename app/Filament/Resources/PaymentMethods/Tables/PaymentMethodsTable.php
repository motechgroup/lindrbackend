<?php

namespace App\Filament\Resources\PaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('code')
                    ->fontFamily('mono')
                    ->searchable(),
                TextColumn::make('provider_code')
                    ->label('Provider')
                    ->badge()
                    ->searchable(),
                IconColumn::make('enabled')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('supported_countries')
                    ->label('Countries')
                    ->badge()
                    ->getStateUsing(fn ($record) => is_array($record->supported_countries) ? implode(', ', $record->supported_countries) : 'All'),
                TextColumn::make('supported_currencies')
                    ->label('Currencies')
                    ->badge()
                    ->getStateUsing(fn ($record) => is_array($record->supported_currencies) ? implode(', ', $record->supported_currencies) : 'All'),
                TextColumn::make('minimum_amount')
                    ->money('KES')
                    ->sortable(),
                TextColumn::make('maximum_amount')
                    ->money('KES')
                    ->sortable(),
                TextColumn::make('display_order')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('display_order', 'asc')
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
