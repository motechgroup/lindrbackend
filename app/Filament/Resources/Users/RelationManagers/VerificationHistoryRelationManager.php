<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\AdminAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VerificationHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'adminActionsOnUser';

    protected static ?string $title = 'Verification Audit & Action History';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('action')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Timestamp')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('admin.email')
                    ->label('Admin Email')
                    ->default(fn (AdminAction $record) => $record->metadata['admin_email'] ?? 'System Admin')
                    ->searchable(),
                TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'MANUAL_VERIFY', 'VERIFY_USER' => 'success',
                        'MANUAL_REJECT', 'REJECT_VERIFICATION' => 'danger',
                        'MANUAL_RESET' => 'warning',
                        'MANUAL_REVOKE' => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('metadata.previous_status')
                    ->label('Previous Status')
                    ->formatStateUsing(fn ($state) => strtoupper((string) ($state ?? '-'))),
                TextColumn::make('metadata.new_status')
                    ->label('New Status')
                    ->formatStateUsing(fn ($state) => strtoupper((string) ($state ?? '-'))),
                TextColumn::make('reason')
                    ->label('Reason / Admin Note')
                    ->wrap()
                    ->default('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
