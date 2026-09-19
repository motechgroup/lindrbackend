<?php

namespace App\Filament\Resources\Gifts\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GiftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('coin_price')->numeric()->required(),
                TextInput::make('recipient_share_percentage')->numeric()->default(60.0)->prefix('%')->required(),
                TextInput::make('image_url')->maxLength(255),
                TextInput::make('animation_reference')->maxLength(255),
                Toggle::make('is_active')->default(true),
            ]);
    }
}
