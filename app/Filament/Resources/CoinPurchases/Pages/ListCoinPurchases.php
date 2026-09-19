<?php

namespace App\Filament\Resources\CoinPurchases\Pages;

use App\Filament\Resources\CoinPurchases\CoinPurchaseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoinPurchases extends ListRecords
{
    protected static string $resource = CoinPurchaseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
