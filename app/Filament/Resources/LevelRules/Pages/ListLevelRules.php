<?php

namespace App\Filament\Resources\LevelRules\Pages;

use App\Filament\Resources\LevelRules\LevelRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLevelRules extends ListRecords
{
    protected static string $resource = LevelRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
