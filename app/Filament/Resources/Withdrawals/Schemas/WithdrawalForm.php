<?php

namespace App\Filament\Resources\Withdrawals\Schemas;

use App\Enums\WithdrawalStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WithdrawalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'name')->required()->label('Creator'),
                TextInput::make('credits_deducted')->numeric()->required()->label('Credits Deducted'),
                TextInput::make('amount_kes')->numeric()->prefix('KES')->required()->label('Cash Amount (KES)'),
                TextInput::make('cash_amount_usd')->numeric()->prefix('USD')->label('Cash Amount (USD)'),
                TextInput::make('conversion_rate')->numeric()->default(10.0)->label('Conversion Rate'),
                TextInput::make('mpesa_number')->required()->label('M-Pesa Number'),
                TextInput::make('method')->default('mpesa')->required(),
                Select::make('status')
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
                    ])
                    ->required(),
                TextInput::make('provider_reference')->label('Provider Reference'),
                Textarea::make('failure_reason')->columnSpanFull()->label('Failure Reason'),
                Textarea::make('admin_notes')->columnSpanFull()->label('Admin Notes'),
            ]);
    }
}
