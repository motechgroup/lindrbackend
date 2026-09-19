<?php

namespace App\Filament\Resources\CoinPackages\Pages;

use App\Filament\Resources\CoinPackages\CoinPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCoinPackage extends EditRecord
{
    protected static string $resource = CoinPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
