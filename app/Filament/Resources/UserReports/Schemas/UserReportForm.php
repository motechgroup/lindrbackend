<?php

namespace App\Filament\Resources\UserReports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('reporter_id')->relationship('reporter', 'name')->disabled(),
                Select::make('reported_id')->relationship('reported', 'name')->disabled(),
                TextInput::make('reason')->disabled(),
                Textarea::make('description')->disabled()->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'reviewed' => 'Reviewed',
                        'actioned' => 'Actioned',
                        'dismissed' => 'Dismissed',
                    ])
                    ->required(),
                Textarea::make('admin_notes')->columnSpanFull(),
            ]);
    }
}
