<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Services\AdminVerificationService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyUser')
                ->label('Verify User')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Verify this user as a Lindr creator?')
                ->modalDescription(fn () => "Username: {$this->record->username} | Current Status: ".strtoupper($this->record->creator_status ?? 'unverified').' | New Status: VERIFIED')
                ->form([
                    TextInput::make('reason')
                        ->label('Admin Note / Reason (Optional)')
                        ->placeholder('e.g. Test account verification / ID verified')
                        ->maxLength(255),
                ])
                ->action(function (array $data, AdminVerificationService $verificationService) {
                    $verificationService->verifyUser(auth()->user(), $this->record, $data['reason'] ?? null);
                    Notification::make()->title('User Verified Successfully')->success()->send();
                    $this->refreshFormData();
                })
                ->visible(fn () => auth()->user()?->can('manageCreatorVerification', $this->record) && in_array($this->record->creator_status ?? 'unverified', ['unverified', 'none', 'pending', 'rejected', 'revoked'])),

            Action::make('rejectVerification')
                ->label('Reject Verification')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Reject this verification?')
                ->modalDescription(fn () => "Reject creator verification for {$this->record->username}.")
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
                ->action(function (array $data, AdminVerificationService $verificationService) {
                    $reason = $data['reason_category'];
                    if (! empty($data['reason_note'])) {
                        $reason .= ' - '.$data['reason_note'];
                    }
                    $verificationService->rejectVerification(auth()->user(), $this->record, $reason);
                    Notification::make()->title('Verification Rejected')->danger()->send();
                    $this->refreshFormData();
                })
                ->visible(fn () => auth()->user()?->can('manageCreatorVerification', $this->record) && ($this->record->creator_status === 'pending' || in_array($this->record->creator_status, ['unverified', 'none']))),

            Action::make('resetVerification')
                ->label('Reset Verification')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Reset verification status?')
                ->modalDescription(fn () => "Reset {$this->record->username} back to UNVERIFIED state.")
                ->form([
                    TextInput::make('reason')
                        ->label('Reset Reason')
                        ->required()
                        ->placeholder('e.g. Resetting per user request to allow resubmission'),
                ])
                ->action(function (array $data, AdminVerificationService $verificationService) {
                    $verificationService->resetVerification(auth()->user(), $this->record, $data['reason']);
                    Notification::make()->title('Verification Status Reset')->warning()->send();
                    $this->refreshFormData();
                })
                ->visible(fn () => auth()->user()?->can('manageCreatorVerification', $this->record) && in_array($this->record->creator_status, ['rejected', 'revoked', 'pending'])),

            Action::make('revokeVerification')
                ->label('Revoke Verification')
                ->icon('heroicon-o-minus-circle')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading("Revoke this creator's verification?")
                ->modalDescription(fn () => "Revoking verification for verified creator {$this->record->username}. Creator will lose earning privileges for future interactions, but historical ledger entries will be preserved.")
                ->form([
                    TextInput::make('reason')
                        ->label('Revocation Reason')
                        ->required()
                        ->placeholder('e.g. Policy violation / Requested by user'),
                ])
                ->action(function (array $data, AdminVerificationService $verificationService) {
                    $verificationService->revokeVerification(auth()->user(), $this->record, $data['reason']);
                    Notification::make()->title('Creator Verification Revoked')->send();
                    $this->refreshFormData();
                })
                ->visible(fn () => auth()->user()?->can('manageCreatorVerification', $this->record) && ($this->record->is_creator || in_array($this->record->creator_status, ['approved', 'verified']))),

            DeleteAction::make(),
        ];
    }
}
