<?php

namespace App\Filament\Resources\LivenessVerifications;

use App\Filament\Resources\LivenessVerifications\Pages\ListLivenessVerifications;
use App\Models\LivenessVerification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class LivenessVerificationResource extends Resource
{
    protected static ?string $model = LivenessVerification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static \UnitEnum|string|null $navigationGroup = 'Creator Management';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Applicant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->label('Approve Creator')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (LivenessVerification $record) {
                        $record->update([
                            'status' => 'approved',
                            'reviewed_at' => now(),
                        ]);

                        $record->user->update([
                            'is_creator' => true,
                            'creator_status' => 'approved',
                            'liveness_verified_at' => now(),
                        ]);
                    })
                    ->visible(fn (LivenessVerification $record) => $record->status === 'pending'),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (LivenessVerification $record) {
                        $record->update([
                            'status' => 'rejected',
                            'reviewed_at' => now(),
                        ]);

                        $record->user->update([
                            'creator_status' => 'rejected',
                        ]);
                    })
                    ->visible(fn (LivenessVerification $record) => $record->status === 'pending'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLivenessVerifications::route('/'),
        ];
    }
}
