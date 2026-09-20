<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Services\AdminVerificationService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable()->sortable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('creator_status')
                    ->label('Verification')
                    ->badge()
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? 'UNVERIFIED'))
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'verified' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'revoked' => 'gray',
                        default => 'info',
                    })
                    ->sortable(),
                TextColumn::make('wallet.coin_balance')->label('Coins')->sortable(),
                TextColumn::make('wallet.credits')->label('Credits')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),

                Action::make('verifyUser')
                    ->label('Verify User')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Verify this user as a Lindr creator?')
                    ->modalDescription(fn (User $record) => "Username: {$record->username} | Current Status: ".strtoupper($record->creator_status ?? 'unverified').' | New Status: VERIFIED')
                    ->form([
                        TextInput::make('reason')
                            ->label('Admin Note / Reason (Optional)')
                            ->placeholder('e.g. Test account verification / ID verified')
                            ->maxLength(255),
                    ])
                    ->action(function (User $record, array $data, AdminVerificationService $verificationService) {
                        $verificationService->verifyUser(auth()->user(), $record, $data['reason'] ?? null);
                        Notification::make()->title('User Verified Successfully')->success()->send();
                    })
                    ->visible(fn (User $record) => auth()->user()?->can('manageCreatorVerification', $record) && in_array($record->creator_status ?? 'unverified', ['unverified', 'none', 'pending', 'rejected', 'revoked'])),

                Action::make('rejectVerification')
                    ->label('Reject Verification')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Reject this verification?')
                    ->modalDescription(fn (User $record) => "Reject creator verification for {$record->username}.")
                    ->form([
                        Select::make('reason_category')
                            ->label('Rejection Reason')
                            ->options([
                                'Liveness not satisfactory' => 'Liveness not satisfactory',
                                'Verification evidence insufficient' => 'Verification evidence insufficient',
                                'Account issue' => 'Account issue',
                                'Other' => 'Other',
                            ])
                            ->required(),
                        TextInput::make('reason_note')
                            ->label('Additional Details / Note (Optional)')
                            ->placeholder('Enter specific rejection feedback'),
                    ])
                    ->action(function (User $record, array $data, AdminVerificationService $verificationService) {
                        $reason = $data['reason_category'];
                        if (! empty($data['reason_note'])) {
                            $reason .= ' - '.$data['reason_note'];
                        }
                        $verificationService->rejectVerification(auth()->user(), $record, $reason);
                        Notification::make()->title('Verification Rejected')->danger()->send();
                    })
                    ->visible(fn (User $record) => auth()->user()?->can('manageCreatorVerification', $record) && ($record->creator_status === 'pending' || in_array($record->creator_status, ['unverified', 'none']))),

                Action::make('resetVerification')
                    ->label('Reset Verification')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Reset verification status?')
                    ->modalDescription(fn (User $record) => "Reset {$record->username} back to UNVERIFIED state.")
                    ->form([
                        TextInput::make('reason')
                            ->label('Reset Reason')
                            ->required()
                            ->placeholder('e.g. Resetting per user request to allow resubmission'),
                    ])
                    ->action(function (User $record, array $data, AdminVerificationService $verificationService) {
                        $verificationService->resetVerification(auth()->user(), $record, $data['reason']);
                        Notification::make()->title('Verification Status Reset')->warning()->send();
                    })
                    ->visible(fn (User $record) => auth()->user()?->can('manageCreatorVerification', $record) && in_array($record->creator_status, ['rejected', 'revoked', 'pending'])),

                Action::make('revokeVerification')
                    ->label('Revoke Verification')
                    ->icon('heroicon-o-minus-circle')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading("Revoke this creator's verification?")
                    ->modalDescription(fn (User $record) => "Revoking verification for verified creator {$record->username}. Creator will lose earning privileges for future interactions, but historical ledger entries will be preserved.")
                    ->form([
                        TextInput::make('reason')
                            ->label('Revocation Reason')
                            ->required()
                            ->placeholder('e.g. Policy violation / Requested by user'),
                    ])
                    ->action(function (User $record, array $data, AdminVerificationService $verificationService) {
                        $verificationService->revokeVerification(auth()->user(), $record, $data['reason']);
                        Notification::make()->title('Creator Verification Revoked')->send();
                    })
                    ->visible(fn (User $record) => auth()->user()?->can('manageCreatorVerification', $record) && ($record->is_creator || in_array($record->creator_status, ['approved', 'verified']))),

                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete User Account?')
                    ->modalDescription(fn (User $record) => "Are you sure you want to permanently delete user {$record->name} (#{$record->id})? This action cannot be undone.")
                    ->successNotificationTitle('User account deleted permanently.'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
