<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InterestResource\Pages\ListInterests;
use App\Models\Interest;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InterestResource extends Resource
{
    protected static ?string $model = Interest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static UnitEnum|string|null $navigationGroup = 'Discovery & Profiles';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('category')
                    ->placeholder('e.g. Lifestyle, Sports, Tech')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active for Discovery')
                    ->default(true),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category')->searchable()->sortable(),
                IconColumn::make('is_active')->boolean()->label('Active')->sortable(),
                TextColumn::make('sort_order')->sortable(),
                TextColumn::make('users_count')->counts('users')->label('Assigned Users')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Interest $record) {
                        if ($record->users()->count() > 0) {
                            Notification::make()
                                ->warning()
                                ->title('Cannot Delete Interest')
                                ->body("Interest '{$record->name}' is assigned to users. Deactivate it instead to preserve user relationships.")
                                ->send();

                            $action->halt();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInterests::route('/'),
        ];
    }
}
