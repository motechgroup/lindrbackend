<?php

namespace App\Filament\Resources\ModerationActions;

use App\Models\ModerationAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ModerationActionResource extends Resource
{
    protected static ?string $model = ModerationAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static \UnitEnum|string|null $navigationGroup = 'Progression & Safety';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Action ID')->limit(8),
                TextColumn::make('admin.name')->label('Admin'),
                TextColumn::make('targetUser.name')->label('Target User')->searchable(),
                TextColumn::make('action')->badge()->sortable(),
                TextColumn::make('score_penalty')->label('Score Penalty'),
                TextColumn::make('new_status')->label('New Status'),
                TextColumn::make('reason')->limit(30),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListModerationActions::route('/'),
        ];
    }
}
