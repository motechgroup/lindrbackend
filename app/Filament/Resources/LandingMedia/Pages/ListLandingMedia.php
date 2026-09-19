<?php

namespace App\Filament\Resources\LandingMedia\Pages;

use App\Filament\Resources\LandingMedia\LandingMediaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingMedia extends ListRecords
{
    protected static string $resource = LandingMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
