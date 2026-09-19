<?php

namespace App\Filament\Resources\PaymentTransactions;

use App\Filament\Resources\PaymentTransactions\Pages\CreatePaymentTransaction;
use App\Filament\Resources\PaymentTransactions\Pages\EditPaymentTransaction;
use App\Filament\Resources\PaymentTransactions\Pages\ListPaymentTransactions;
use App\Filament\Resources\PaymentTransactions\Schemas\PaymentTransactionForm;
use App\Filament\Resources\PaymentTransactions\Tables\PaymentTransactionsTable;
use App\Models\PaymentTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PaymentTransactionResource extends Resource
{
    protected static ?string $model = PaymentTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PaymentTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentTransactionsTable::configure($table);
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
            'index' => ListPaymentTransactions::route('/'),
            'create' => CreatePaymentTransaction::route('/create'),
            'edit' => EditPaymentTransaction::route('/{record}/edit'),
        ];
    }
}
