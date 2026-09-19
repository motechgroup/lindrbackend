<?php

namespace App\Filament\Resources\LevelRules;

use App\Models\LevelRule;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LevelRuleResource extends Resource
{
    protected static ?string $model = LevelRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static \UnitEnum|string|null $navigationGroup = 'Progression & Safety';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('level')->numeric()->required(),
            TextInput::make('name')->required(),
            TextInput::make('threshold_score')->numeric()->required(),
            TextInput::make('exposure_multiplier')->numeric()->step('0.1')->required(),
            TextInput::make('creator_commission_pct')->numeric()->step('0.1')->required(),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('threshold_score')->label('Min Points')->sortable(),
                TextColumn::make('exposure_multiplier')->label('Exposure Multiplier')->sortable(),
                TextColumn::make('creator_commission_pct')->label('Creator Share %')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('level', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLevelRules::route('/'),
        ];
    }
}
