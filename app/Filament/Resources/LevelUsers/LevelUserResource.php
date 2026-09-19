<?php

namespace App\Filament\Resources\LevelUsers;

use App\Filament\Resources\LevelUsers\Pages\ListLevelUsers;
use App\Models\User;
use App\Services\LevelService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class LevelUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'Level Users';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static \UnitEnum|string|null $navigationGroup = 'Progression & Safety';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('current_level')
                    ->label('Level')
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);
                        $data = $service->getUserLevelData($record);

                        return "⭐ Level {$data['level']} ({$data['level_name']})";
                    }),
                Tables\Columns\TextColumn::make('score')
                    ->label('Total Points')
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);

                        return $service->getUserLevelData($record)['score'];
                    })->sortable(),
                Tables\Columns\TextColumn::make('membership_days')
                    ->label('Membership Days')
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);

                        return $service->getUserLevelData($record)['membership_days'];
                    }),
                Tables\Columns\TextColumn::make('topup_points')
                    ->label('Topup Points')
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);

                        return $service->getUserLevelData($record)['breakdown']['topup_points'];
                    }),
                Tables\Columns\TextColumn::make('spotlight_points')
                    ->label('Spotlight Points')
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);

                        return $service->getUserLevelData($record)['breakdown']['spotlight_points'];
                    }),
                Tables\Columns\TextColumn::make('community_standing')
                    ->label('Standing')
                    ->badge()
                    ->getStateUsing(function (User $record) {
                        $service = app(LevelService::class);

                        return $service->getUserLevelData($record)['community_standing'];
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'GOOD_STANDING' => 'success',
                        'WARNING' => 'warning',
                        default => 'danger',
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->label('Recalculate Level')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        $service = app(LevelService::class);
                        $adminId = auth()->id();
                        $data = $service->recalculateUserLevel($record, 'ADMIN_RECALCULATION', $adminId);

                        Notification::make()
                            ->title('User Level Recalculated')
                            ->body("User {$record->name} level is now Level {$data['level']} with {$data['score']} points.")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLevelUsers::route('/'),
        ];
    }
}
