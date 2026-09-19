<?php

namespace App\Filament\Resources\LandingMedia\Pages;

use App\Filament\Resources\LandingMedia\LandingMediaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingMedia extends EditRecord
{
    protected static string $resource = LandingMediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
