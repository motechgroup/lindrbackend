<?php

namespace App\Filament\Resources\CallSessions;

use App\Filament\Resources\CallSessions\Pages\CreateCallSession;
use App\Filament\Resources\CallSessions\Pages\EditCallSession;
use App\Filament\Resources\CallSessions\Pages\ListCallSessions;
use App\Filament\Resources\CallSessions\Schemas\CallSessionForm;
use App\Filament\Resources\CallSessions\Tables\CallSessionsTable;
use App\Models\CallSession;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CallSessionResource extends Resource
{
    protected static ?string $model = CallSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $navigationLabel = 'Video Calls';

    protected static ?string $modelLabel = 'Video Call Session';

    public static function form(Schema $schema): Schema
    {
        return CallSessionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallSessionsTable::configure($table);
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
            'index' => ListCallSessions::route('/'),
            'create' => CreateCallSession::route('/create'),
            'edit' => EditCallSession::route('/{record}/edit'),
        ];
    }
}
