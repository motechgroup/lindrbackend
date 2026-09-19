<?php

namespace App\Filament\Resources\UserAppeals\Pages;

use App\Filament\Resources\UserAppeals\UserAppealResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserAppeals extends ListRecords
{
    protected static string $resource = UserAppealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
