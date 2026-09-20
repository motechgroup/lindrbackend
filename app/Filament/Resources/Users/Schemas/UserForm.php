<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Account & Identity')
                    ->components([
                        TextInput::make('id')->disabled()->label('User ID'),
                        TextInput::make('name')->required()->maxLength(255)->label('Full Name'),
                        TextInput::make('profile.display_name')->disabled()->label('Lindr Username'),
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
                        TextInput::make('profile.gender')->disabled()->label('Gender'),
                        TextInput::make('profile.date_of_birth')->disabled()->label('Date of Birth'),
                        TextInput::make('profile.country_code')->disabled()->label('Country Code'),
                    ])->columns(3),

                Section::make('Creator Verification & Financial Eligibility')
                    ->components([
                        TextInput::make('creator_status')
                            ->disabled()
                            ->label('Creator Status')
                            ->formatStateUsing(fn ($state) => strtoupper($state ?? 'UNVERIFIED')),
                        TextInput::make('is_creator')
                            ->disabled()
                            ->label('Verified Creator?')
                            ->formatStateUsing(fn ($state) => $state ? 'YES (Verified Creator)' : 'NO (Unverified)'),
                        TextInput::make('liveness_verified_at')
                            ->disabled()
                            ->label('Liveness / Admin Verified At'),
                        TextInput::make('mpesa_phone')
                            ->disabled()
                            ->label('M-Pesa Payout Phone'),
                        TextInput::make('mpesa_phone_verified')
                            ->disabled()
                            ->label('M-Pesa Verified?')
                            ->formatStateUsing(fn ($state) => $state ? 'YES' : 'NO'),
                    ])->columns(3),
            ]);
    }
}
