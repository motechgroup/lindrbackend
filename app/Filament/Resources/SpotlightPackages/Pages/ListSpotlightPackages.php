<?php

namespace App\Filament\Resources\SpotlightPackages\Pages;

use App\Filament\Resources\SpotlightPackages\SpotlightPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpotlightPackages extends ListRecords
{
    protected static string $resource = SpotlightPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
