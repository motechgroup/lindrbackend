<?php

namespace App\Filament\Resources\UserAppeals\Pages;

use App\Filament\Resources\UserAppeals\UserAppealResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserAppeal extends EditRecord
{
    protected static string $resource = UserAppealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
