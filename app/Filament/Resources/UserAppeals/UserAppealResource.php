<?php

namespace App\Filament\Resources\UserAppeals;

use App\Filament\Resources\UserAppeals\Pages\CreateUserAppeal;
use App\Filament\Resources\UserAppeals\Pages\EditUserAppeal;
use App\Filament\Resources\UserAppeals\Pages\ListUserAppeals;
use App\Filament\Resources\UserAppeals\Schemas\UserAppealForm;
use App\Filament\Resources\UserAppeals\Tables\UserAppealsTable;
use App\Models\UserAppeal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserAppealResource extends Resource
{
    protected static ?string $model = UserAppeal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return UserAppealForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserAppealsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserAppeals::route('/'),
            'create' => CreateUserAppeal::route('/create'),
            'edit' => EditUserAppeal::route('/{record}/edit'),
        ];
    }
}
