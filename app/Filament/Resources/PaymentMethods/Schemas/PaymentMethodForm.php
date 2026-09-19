<?php

namespace App\Filament\Resources\PaymentMethods\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('name')
                    ->required(),
                TextInput::make('provider_code')
                    ->required(),
                Toggle::make('enabled')
                    ->required(),
                Textarea::make('supported_countries')
                    ->columnSpanFull(),
                Textarea::make('supported_currencies')
                    ->columnSpanFull(),
                TextInput::make('minimum_amount')
                    ->numeric(),
                TextInput::make('maximum_amount')
                    ->numeric(),
                TextInput::make('display_order')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
