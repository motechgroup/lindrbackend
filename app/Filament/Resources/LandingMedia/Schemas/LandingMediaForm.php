<?php

namespace App\Filament\Resources\LandingMedia\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LandingMediaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Media Details')
                    ->components([
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Unique asset key used by the landing page (e.g. hero_lifestyle, match_screen)'),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Image & Alt Text')
                    ->components([
                        FileUpload::make('file_path')
                            ->label('Upload Image')
                            ->disk('public')
                            ->directory('landing')
                            ->image()
                            ->maxSize(5120)
                            ->helperText('Stored in public disk under storage/app/public/landing/'),
                        TextInput::make('alt_text')
                            ->label('Alt Text (Accessibility & SEO)')
                            ->maxLength(255),
                    ]),

                Section::make('Publishing & Ordering')
                    ->components([
                        TextInput::make('display_order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active / Visible')
                            ->default(true),
                    ])->columns(2),
            ]);
    }
}
