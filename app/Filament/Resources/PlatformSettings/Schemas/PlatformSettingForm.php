<?php

namespace App\Filament\Resources\PlatformSettings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PlatformSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')->required()->maxLength(255),
                TextInput::make('value')->required()->maxLength(255),
                Textarea::make('description')->columnSpanFull(),
            ]);
    }
}
