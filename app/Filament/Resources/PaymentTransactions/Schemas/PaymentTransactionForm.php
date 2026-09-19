<?php

namespace App\Filament\Resources\PaymentTransactions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PaymentTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('public_reference')
                    ->required(),
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required(),
                Select::make('package_id')
                    ->relationship('package', 'name')
                    ->required(),
                TextInput::make('provider_code')
                    ->required(),
                TextInput::make('payment_method_code')
                    ->required(),
                TextInput::make('country')
                    ->required()
                    ->default('KE'),
                TextInput::make('currency')
                    ->required()
                    ->default('KES'),
                TextInput::make('amount')
                    ->required()
                    ->numeric(),
                TextInput::make('expected_coins')
                    ->required()
                    ->numeric(),
                TextInput::make('provider_reference'),
                TextInput::make('status')
                    ->required()
                    ->default('pending'),
                TextInput::make('idempotency_key'),
                Textarea::make('metadata')
                    ->columnSpanFull(),
                Textarea::make('error_message')
                    ->columnSpanFull(),
            ]);
    }
}
