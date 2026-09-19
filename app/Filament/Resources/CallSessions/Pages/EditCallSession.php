<?php

namespace App\Filament\Resources\CallSessions\Pages;

use App\Filament\Resources\CallSessions\CallSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCallSession extends EditRecord
{
    protected static string $resource = CallSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
