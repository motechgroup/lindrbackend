<?php

namespace App\Filament\Resources\Withdrawals\Tables;

use App\Enums\WithdrawalStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class WithdrawalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Withdrawal ID')->searchable(),
                TextColumn::make('user.name')->label('Creator')->searchable()->sortable(),
                TextColumn::make('credits_deducted')->label('Credits')->sortable(),
                TextColumn::make('amount_kes')->label('Amount (KES)')->money('KES')->sortable(),
                TextColumn::make('mpesa_number')
                    ->label('M-Pesa Number')
                    ->formatStateUsing(fn ($state) => $state ? Str::mask($state, '*', 4, 4) : 'N/A')
                    ->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('provider_reference')->label('Provider Ref')->searchable(),
                TextColumn::make('failure_reason')->label('Failure Reason')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        WithdrawalStatus::Pending->value => 'Pending',
                        WithdrawalStatus::Approved->value => 'Approved',
                        WithdrawalStatus::Processing->value => 'Processing',
                        WithdrawalStatus::Paid->value => 'Paid',
                        WithdrawalStatus::Success->value => 'Success',
                        WithdrawalStatus::Rejected->value => 'Rejected',
                        WithdrawalStatus::Failed->value => 'Failed',
                        WithdrawalStatus::Cancelled->value => 'Cancelled',
                        WithdrawalStatus::Reversed->value => 'Reversed',
                    ]),
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
