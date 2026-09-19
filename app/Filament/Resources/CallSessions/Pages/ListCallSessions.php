<?php

namespace App\Filament\Resources\CallSessions\Pages;

use App\Filament\Resources\CallSessions\CallSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallSessions extends ListRecords
{
    protected static string $resource = CallSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
