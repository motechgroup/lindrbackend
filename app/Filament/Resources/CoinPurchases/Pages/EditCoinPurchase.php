<?php

namespace App\Filament\Resources\CoinPurchases\Pages;

use App\Filament\Resources\CoinPurchases\CoinPurchaseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoinPurchase extends EditRecord
{
    protected static string $resource = CoinPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
