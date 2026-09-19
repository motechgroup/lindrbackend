<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
                Select::make('role')
                    ->options([
                        UserRole::Male->value => 'Male',
                        UserRole::Female->value => 'Female',
                        UserRole::Admin->value => 'Admin',
                    ])
                    ->required(),
                Select::make('status')
                    ->options([
                        UserStatus::Active->value => 'Active',
                        UserStatus::Suspended->value => 'Suspended',
                        UserStatus::Pending->value => 'Pending',
                    ])
                    ->required(),
            ]);
    }
}
