<?php

namespace App\Filament\Resources\CoinPackages\Pages;

use App\Filament\Resources\CoinPackages\CoinPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCoinPackages extends ListRecords
{
    protected static string $resource = CoinPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
