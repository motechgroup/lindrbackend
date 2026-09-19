<?php

namespace App\Filament\Resources\LandingSettings\Pages;

use App\Filament\Resources\LandingSettings\LandingSettingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLandingSetting extends EditRecord
{
    protected static string $resource = LandingSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
