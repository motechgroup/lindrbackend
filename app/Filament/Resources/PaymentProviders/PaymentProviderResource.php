<?php

namespace App\Filament\Resources\PaymentProviders;

use App\Filament\Resources\PaymentProviders\Pages\CreatePaymentProvider;
use App\Filament\Resources\PaymentProviders\Pages\EditPaymentProvider;
use App\Filament\Resources\PaymentProviders\Pages\ListPaymentProviders;
use App\Filament\Resources\PaymentProviders\Schemas\PaymentProviderForm;
use App\Filament\Resources\PaymentProviders\Tables\PaymentProvidersTable;
use App\Models\PaymentProvider;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProvider::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PaymentProviderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentProvidersTable::configure($table);
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
            'index' => ListPaymentProviders::route('/'),
            'create' => CreatePaymentProvider::route('/create'),
            'edit' => EditPaymentProvider::route('/{record}/edit'),
        ];
    }
}
