<?php

namespace App\Filament\Resources\WalletTransactions\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WalletTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')->disabled(),
                TextInput::make('user_id')->disabled(),
                TextInput::make('transaction_type')->disabled(),
                TextInput::make('amount')->numeric()->disabled(),
                TextInput::make('balance_before')->numeric()->disabled(),
                TextInput::make('balance_after')->numeric()->disabled(),
                TextInput::make('reference_type')->disabled(),
                TextInput::make('reference_id')->disabled(),
                TextInput::make('description')->disabled(),
                TextInput::make('status')->disabled(),
            ]);
    }
}
