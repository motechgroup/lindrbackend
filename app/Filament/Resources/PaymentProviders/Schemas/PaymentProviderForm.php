<?php

namespace App\Filament\Resources\PaymentProviders\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PaymentProviderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->description('Basic provider configuration, priority ranking, and status.')
                    ->components([
                        TextInput::make('name')
                            ->label('Provider Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Provider Code')
                            ->required()
                            ->disabled(fn ($record) => $record !== null)
                            ->maxLength(50),
                        Toggle::make('enabled')
                            ->label('Enabled')
                            ->default(true),
                        Toggle::make('test_mode')
                            ->label('Test / Sandbox Mode')
                            ->default(true),
                        TextInput::make('priority')
                            ->label('Priority Ranking')
                            ->numeric()
                            ->default(1)
                            ->required(),
                        Select::make('status')
                            ->label('Provider Health Status')
                            ->options([
                                'active' => 'Active',
                                'degraded' => 'Degraded',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Regional & Currency Scope')
                    ->description('Specify supported customer countries and currency codes.')
                    ->components([
                        TagsInput::make('supported_countries')
                            ->label('Supported Countries (ISO 2-letter)')
                            ->placeholder('Add country e.g. KE, NG, GH, US')
                            ->separator(','),
                        TagsInput::make('supported_currencies')
                            ->label('Supported Currencies (ISO 3-letter)')
                            ->placeholder('Add currency e.g. KES, NGN, GHS, USD')
                            ->separator(','),
                    ])
                    ->columns(2),

                // Safaricom M-Pesa Daraja Dedicated Inputs
                Section::make('Safaricom M-Pesa (Daraja API) Credentials')
                    ->description('Enter credentials obtained from the Safaricom Developer Portal (Daraja).')
                    ->visible(fn ($get) => $get('code') === 'mpesa' || $get('code') === null)
                    ->components([
                        TextInput::make('configuration.consumer_key')
                            ->label('Daraja Consumer Key')
                            ->password()
                            ->revealable()
                            ->placeholder('e.g. 5xG8yZ...'),
                        TextInput::make('configuration.consumer_secret')
                            ->label('Daraja Consumer Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('e.g. 8yZ5xG...'),
                        TextInput::make('configuration.shortcode')
                            ->label('Business Shortcode / Paybill / Till')
                            ->placeholder('174379'),
                        TextInput::make('configuration.passkey')
                            ->label('STK Push Passkey')
                            ->password()
                            ->revealable()
                            ->placeholder('e.g. bfb279f9aa9b...'),
                        Select::make('configuration.environment')
                            ->label('M-Pesa Daraja Environment')
                            ->options([
                                'sandbox' => 'Sandbox (Testing)',
                                'production' => 'Production (Live Safaricom API)',
                            ])
                            ->default('sandbox'),
                        TextInput::make('configuration.callback_url')
                            ->label('STK Callback URL')
                            ->url()
                            ->placeholder('https://yourdomain.com/api/v1/webhooks/mpesa'),
                    ])
                    ->columns(2),

                // KoraPay Dedicated Inputs
                Section::make('KoraPay Credentials')
                    ->description('Enter credentials obtained from the KoraPay Merchant Dashboard.')
                    ->visible(fn ($get) => $get('code') === 'korapay')
                    ->components([
                        TextInput::make('configuration.public_key')
                            ->label('KoraPay Public Key')
                            ->placeholder('pk_live_...'),
                        TextInput::make('configuration.secret_key')
                            ->label('KoraPay Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('sk_live_...'),
                        TextInput::make('webhook_secret')
                            ->label('KoraPay Webhook Signature Secret')
                            ->password()
                            ->revealable(),
                    ])
                    ->columns(2),

                // Flutterwave Dedicated Inputs
                Section::make('Flutterwave Credentials')
                    ->description('Enter credentials obtained from the Flutterwave Developer Dashboard.')
                    ->visible(fn ($get) => $get('code') === 'flutterwave')
                    ->components([
                        TextInput::make('configuration.public_key')
                            ->label('Flutterwave Public Key')
                            ->placeholder('FLWPUBK_LIVE-...'),
                        TextInput::make('configuration.secret_key')
                            ->label('Flutterwave Secret Key')
                            ->password()
                            ->revealable()
                            ->placeholder('FLWSECK_LIVE-...'),
                        TextInput::make('webhook_secret')
                            ->label('Flutterwave Webhook Secret (verif-hash)')
                            ->password()
                            ->revealable(),
                    ])
                    ->columns(2),

                // Google Pay Dedicated Inputs
                Section::make('Google Pay Configuration')
                    ->description('Configure Google Pay merchant settings and downstream payment processor.')
                    ->visible(fn ($get) => $get('code') === 'google_pay')
                    ->components([
                        TextInput::make('configuration.merchant_id')
                            ->label('Google Pay Merchant ID')
                            ->placeholder('12345678901234567890'),
                        TextInput::make('configuration.merchant_name')
                            ->label('Google Pay Merchant Name')
                            ->placeholder('Lindr Dating'),
                        Select::make('configuration.processor')
                            ->label('Downstream Processor')
                            ->options([
                                'flutterwave' => 'Flutterwave',
                                'stripe' => 'Stripe',
                            ])
                            ->default('flutterwave'),
                        Select::make('configuration.environment')
                            ->label('Google Pay Environment')
                            ->options([
                                'TEST' => 'Test Environment',
                                'PRODUCTION' => 'Production Environment',
                            ])
                            ->default('TEST'),
                    ])
                    ->columns(2),
            ]);
    }
}
