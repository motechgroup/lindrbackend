<?php

namespace App\Filament\Resources\CoinPackages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CoinPackageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('coin_amount')->numeric()->required(),
                TextInput::make('bonus_coins')->numeric()->default(0)->required(),
                TextInput::make('price_kes')->numeric()->prefix('KES')->required(),
                Toggle::make('is_active')->default(true),
                TextInput::make('display_order')->numeric()->default(0),
            ]);
    }
}
