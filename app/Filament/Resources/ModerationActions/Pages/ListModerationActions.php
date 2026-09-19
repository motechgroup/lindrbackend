<?php

namespace App\Filament\Resources\ModerationActions\Pages;

use App\Filament\Resources\ModerationActions\ModerationActionResource;
use Filament\Resources\Pages\ListRecords;

class ListModerationActions extends ListRecords
{
    protected static string $resource = ModerationActionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
