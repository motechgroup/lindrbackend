<?php

namespace App\Filament\Resources\CallSessions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CallSessionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('caller_id')
                    ->relationship('caller', 'name')
                    ->required(),
                Select::make('receiver_id')
                    ->relationship('receiver', 'name')
                    ->required(),
                TextInput::make('call_type')
                    ->required()
                    ->default('audio'),
                TextInput::make('room_name')
                    ->required(),
                TextInput::make('rate_per_minute')
                    ->required()
                    ->numeric()
                    ->default(20),
                TextInput::make('status')
                    ->required()
                    ->default('initiated'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('ended_at'),
                TextInput::make('duration_seconds')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('coins_charged')
                    ->required()
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('connected_at'),
                TextInput::make('creator_credits_earned')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('lindr_share')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('creator_commission_pct')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('end_reason'),
            ]);
    }
}
