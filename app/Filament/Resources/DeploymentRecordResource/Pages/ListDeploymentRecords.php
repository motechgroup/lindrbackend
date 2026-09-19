<?php

namespace App\Filament\Resources\DeploymentRecordResource\Pages;

use App\Filament\Resources\DeploymentRecordResource;
use Filament\Resources\Pages\ListRecords;

class ListDeploymentRecords extends ListRecords
{
    protected static string $resource = DeploymentRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
