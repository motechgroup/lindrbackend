<?php

namespace App\Filament\Resources\LivenessVerifications\Pages;

use App\Filament\Resources\LivenessVerifications\LivenessVerificationResource;
use Filament\Resources\Pages\ListRecords;

class ListLivenessVerifications extends ListRecords
{
    protected static string $resource = LivenessVerificationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
