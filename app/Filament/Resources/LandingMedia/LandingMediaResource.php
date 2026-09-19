<?php

namespace App\Filament\Resources\LandingMedia;

use App\Filament\Resources\LandingMedia\Pages\CreateLandingMedia;
use App\Filament\Resources\LandingMedia\Pages\EditLandingMedia;
use App\Filament\Resources\LandingMedia\Pages\ListLandingMedia;
use App\Filament\Resources\LandingMedia\Schemas\LandingMediaForm;
use App\Filament\Resources\LandingMedia\Tables\LandingMediaTable;
use App\Models\LandingMedia;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LandingMediaResource extends Resource
{
    protected static ?string $model = LandingMedia::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static \UnitEnum|string|null $navigationGroup = 'Landing Page CMS';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return LandingMediaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingMediaTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingMedia::route('/'),
            'create' => CreateLandingMedia::route('/create'),
            'edit' => EditLandingMedia::route('/{record}/edit'),
        ];
    }
}
