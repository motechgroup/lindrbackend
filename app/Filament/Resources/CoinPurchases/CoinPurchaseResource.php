<?php

namespace App\Filament\Resources\CoinPurchases;

use App\Filament\Resources\CoinPurchases\Pages\CreateCoinPurchase;
use App\Filament\Resources\CoinPurchases\Pages\EditCoinPurchase;
use App\Filament\Resources\CoinPurchases\Pages\ListCoinPurchases;
use App\Filament\Resources\CoinPurchases\Schemas\CoinPurchaseForm;
use App\Filament\Resources\CoinPurchases\Tables\CoinPurchasesTable;
use App\Models\CoinPurchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CoinPurchaseResource extends Resource
{
    protected static ?string $model = CoinPurchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CoinPurchaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CoinPurchasesTable::configure($table);
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
            'index' => ListCoinPurchases::route('/'),
            'create' => CreateCoinPurchase::route('/create'),
            'edit' => EditCoinPurchase::route('/{record}/edit'),
        ];
    }
}
