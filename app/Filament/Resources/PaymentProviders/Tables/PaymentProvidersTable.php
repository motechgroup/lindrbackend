<?php

namespace App\Filament\Resources\PaymentProviders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class PaymentProvidersTable
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
                ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->sortable(),
                ToggleColumn::make('test_mode')
                    ->label('Test Mode'),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('supported_countries')
                    ->label('Countries')
                    ->badge()
                    ->getStateUsing(fn ($record) => is_array($record->supported_countries) ? implode(', ', $record->supported_countries) : ''),
                TextColumn::make('supported_currencies')
                    ->label('Currencies')
                    ->badge()
                    ->getStateUsing(fn ($record) => is_array($record->supported_currencies) ? implode(', ', $record->supported_currencies) : ''),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'degraded' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('priority', 'asc')
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
