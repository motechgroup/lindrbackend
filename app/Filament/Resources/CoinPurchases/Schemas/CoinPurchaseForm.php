<?php

namespace App\Filament\Resources\CoinPurchases\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CoinPurchaseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')->relationship('user', 'name')->required(),
                Select::make('package_id')->relationship('package', 'name')->required(),
                TextInput::make('amount_kes')->numeric()->prefix('KES')->required(),
                TextInput::make('coins_credited')->numeric()->required(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'successful' => 'Successful',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                TextInput::make('phone_number')->required(),
                TextInput::make('mpesa_receipt_number'),
            ]);
    }
}
