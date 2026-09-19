<?php

namespace App\Filament\Resources\LandingSettings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LandingSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Setting Information')
                    ->components([
                        TextInput::make('key')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Setting identifier key (e.g. site_title, google_play_url)'),
                        TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Human-readable setting title for admin dashboard'),
                        Select::make('group')
                            ->options([
                                'general' => 'General',
                                'hero' => 'Hero Section',
                                'app_links' => 'App Store Links',
                                'contact' => 'Contact Info',
                                'social' => 'Social Media',
                                'seo' => 'SEO & Meta Tags',
                                'footer' => 'Footer',
                            ])
                            ->default('general')
                            ->required(),
                        Textarea::make('value')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Setting value. Leave store link values blank to automatically hide store download buttons on the landing page'),
                    ])->columns(3),
            ]);
    }
}
