<?php

namespace App\Filament\Resources\CommunityGuidelines;

use App\Models\CommunityGuideline;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CommunityGuidelineResource extends Resource
{
    protected static ?string $model = CommunityGuideline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static \UnitEnum|string|null $navigationGroup = 'Progression & Safety';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('version')->required(),
            TextInput::make('title')->required(),
            Select::make('status')
                ->options([
                    'draft' => 'Draft',
                    'published' => 'Published',
                    'retired' => 'Retired',
                ])
                ->default('draft')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version')->sortable(),
                TextColumn::make('title')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('published_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunityGuidelines::route('/'),
        ];
    }
}
