<?php

namespace App\Filament\Resources\SpotlightPackages;

use App\Models\SpotlightPackage;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpotlightPackageResource extends Resource
{
    protected static ?string $model = SpotlightPackage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static \UnitEnum|string|null $navigationGroup = 'Progression & Safety';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required(),
            TextInput::make('duration_minutes')->label('Duration (Minutes)')->numeric()->required(),
            TextInput::make('token_cost')->label('Token Price')->numeric()->required(),
            TextInput::make('boost_multiplier')->label('Priority Boost Multiplier')->numeric()->step('0.1')->required(),
            Textarea::make('description')->rows(3),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('duration_minutes')->label('Duration (Mins)')->sortable(),
                TextColumn::make('token_cost')->label('Cost (Tokens)')->sortable(),
                TextColumn::make('boost_multiplier')->label('Boost Multiplier')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSpotlightPackages::route('/'),
        ];
    }
}
