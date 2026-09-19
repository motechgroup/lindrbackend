<?php

namespace App\Filament\Resources\CallSessions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CallSessionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('caller.name')
                    ->searchable(),
                TextColumn::make('receiver.name')
                    ->searchable(),
                TextColumn::make('call_type')
                    ->searchable(),
                TextColumn::make('room_name')
                    ->searchable(),
                TextColumn::make('rate_per_minute')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coins_charged')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('connected_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('creator_credits_earned')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lindr_share')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('creator_commission_pct')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('end_reason')
                    ->searchable(),
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
