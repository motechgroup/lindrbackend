<?php

namespace App\Filament\Resources\CommunityGuidelines\Pages;

use App\Filament\Resources\CommunityGuidelines\CommunityGuidelineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityGuidelines extends ListRecords
{
    protected static string $resource = CommunityGuidelineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
