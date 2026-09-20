<?php

namespace App\Filament\Pages;

use App\Models\PlatformSetting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MonetizationSettingsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static UnitEnum|string|null $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Monetization & Rates';

    protected static ?string $title = 'Monetization & Commission Settings';

    protected string $view = 'filament.pages.monetization-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'call_female_creator_share_pct' => (float) PlatformSetting::get('call_female_creator_share_pct', 70.0),
            'call_male_creator_share_pct' => (float) PlatformSetting::get('call_male_creator_share_pct', 70.0),
            'chat_female_creator_share_pct' => (float) PlatformSetting::get('chat_female_creator_share_pct', 60.0),
            'chat_male_creator_share_pct' => (float) PlatformSetting::get('chat_male_creator_share_pct', 60.0),
            'gift_female_creator_share_pct' => (float) PlatformSetting::get('gift_female_creator_share_pct', 60.0),
            'gift_male_creator_share_pct' => (float) PlatformSetting::get('gift_male_creator_share_pct', 60.0),
            'video_call_rate_per_minute' => (int) PlatformSetting::get('video_call_rate_per_minute', 30),
            'audio_call_rate_per_minute' => (int) PlatformSetting::get('audio_call_rate_per_minute', 20),
            'message_cost' => (int) PlatformSetting::get('message_cost', PlatformSetting::get('chat_coins', 5)),
            'matching_token_cost' => (int) PlatformSetting::get('matching_token_cost', PlatformSetting::get('match_coins', 50)),
            'credits_per_usd' => (float) PlatformSetting::get('credits_per_usd', 10.0),
            'minimum_withdrawal_credits' => (int) PlatformSetting::get('minimum_withdrawal_credits', 100),
            'payout_number_change_hold_hours' => (int) PlatformSetting::get('payout_number_change_hold_hours', 48),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('📞 Video & Audio Call Creator Commission & Rates')
                    ->description('Set percentage (%) of call token charges awarded as creator credits and token per-minute call rates.')
                    ->icon(Heroicon::OutlinedVideoCamera)
                    ->components([
                        TextInput::make('call_female_creator_share_pct')
                            ->label('Female Creator Call Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default: 70.0% of video/audio call token charges awarded to female creators.'),
                        TextInput::make('call_male_creator_share_pct')
                            ->label('Male Creator Call Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default: 70.0% of video/audio call token charges awarded to male creators.'),
                        TextInput::make('video_call_rate_per_minute')
                            ->label('Video Call Rate (Tokens / Min)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Token cost per minute charged to caller for video calls (Default: 30).'),
                        TextInput::make('audio_call_rate_per_minute')
                            ->label('Audio Call Rate (Tokens / Min)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Token cost per minute charged to caller for audio calls (Default: 20).'),
                    ])
                    ->columns(2),

                Section::make('💬 Paid Chat & Match Coins Settings')
                    ->description('Configure chat coins per sent message, match coins per instant match search, and creator message commission.')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->components([
                        TextInput::make('chat_female_creator_share_pct')
                            ->label('Female Creator Chat Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default: 60.0% of paid chat message tokens awarded to female creators.'),
                        TextInput::make('chat_male_creator_share_pct')
                            ->label('Male Creator Chat Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default: 60.0% of paid chat message tokens awarded to male creators.'),
                        TextInput::make('message_cost')
                            ->label('💬 Chat Coins (Tokens / Paid Message)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Chat coins debited from sender per message sent to a creator (Default: 5 coins).'),
                        TextInput::make('matching_token_cost')
                            ->label('🎯 Match Coins (Tokens / Instant Match)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Match coins debited for instant profile match unlock broadcast (Default: 50 coins).'),
                    ])
                    ->columns(2),

                Section::make('🎁 Virtual Gift Creator Commission')
                    ->description('Set default percentage (%) of gift coin value awarded as creator credits.')
                    ->icon(Heroicon::OutlinedGift)
                    ->components([
                        TextInput::make('gift_female_creator_share_pct')
                            ->label('Female Creator Gift Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default platform fallback: 60.0% of gift coin price awarded to female creators.'),
                        TextInput::make('gift_male_creator_share_pct')
                            ->label('Male Creator Gift Share (%)')
                            ->numeric()
                            ->suffix('%')
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->helperText('Default platform fallback: 60.0% of gift coin price awarded to male creators.'),
                    ])
                    ->columns(2),

                Section::make('💸 Withdrawal & Payout Rules')
                    ->description('Configure creator credit redemption values and withdrawal security rules.')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->components([
                        TextInput::make('credits_per_usd')
                            ->label('Credits per $1.00 USD Payout')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Creator credits required per $1 USD (Default: 10.0 credits = $1 USD).'),
                        TextInput::make('minimum_withdrawal_credits')
                            ->label('Minimum Withdrawal (Credits)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->helperText('Minimum credits required before requesting M-Pesa payout (Default: 100).'),
                        TextInput::make('payout_number_change_hold_hours')
                            ->label('M-Pesa Change Hold (Hours)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->helperText('Security hold period after updating payout phone number (Default: 48 hrs).'),
                    ])
                    ->columns(3),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PlatformSetting::set('call_female_creator_share_pct', (string) $data['call_female_creator_share_pct'], 'Percentage of call token revenue awarded to female creator');
        PlatformSetting::set('call_male_creator_share_pct', (string) $data['call_male_creator_share_pct'], 'Percentage of call token revenue awarded to male creator');

        PlatformSetting::set('chat_female_creator_share_pct', (string) $data['chat_female_creator_share_pct'], 'Percentage of chat token revenue awarded to female creator');
        PlatformSetting::set('chat_male_creator_share_pct', (string) $data['chat_male_creator_share_pct'], 'Percentage of chat token revenue awarded to male creator');

        PlatformSetting::set('gift_female_creator_share_pct', (string) $data['gift_female_creator_share_pct'], 'Percentage of gift token revenue awarded to female creator');
        PlatformSetting::set('gift_male_creator_share_pct', (string) $data['gift_male_creator_share_pct'], 'Percentage of gift token revenue awarded to male creator');

        PlatformSetting::set('video_call_rate_per_minute', (string) $data['video_call_rate_per_minute'], 'Video call token rate per minute');
        PlatformSetting::set('audio_call_rate_per_minute', (string) $data['audio_call_rate_per_minute'], 'Audio call token rate per minute');

        PlatformSetting::set('message_cost', (string) $data['message_cost'], 'Token cost per sent chat message to a verified creator');
        PlatformSetting::set('chat_coins', (string) $data['message_cost'], 'Chat coins cost per sent chat message');

        PlatformSetting::set('matching_token_cost', (string) $data['matching_token_cost'], 'Token cost for instant profile match unlock');
        PlatformSetting::set('match_coins', (string) $data['matching_token_cost'], 'Match coins cost per instant match');

        PlatformSetting::set('credits_per_usd', (string) $data['credits_per_usd'], 'Creator credits required per $1.00 USD payout value');
        PlatformSetting::set('minimum_withdrawal_credits', (string) $data['minimum_withdrawal_credits'], 'Minimum withdrawal threshold in creator credits');
        PlatformSetting::set('payout_number_change_hold_hours', (string) $data['payout_number_change_hold_hours'], 'Payout hold period in hours after updating M-Pesa phone number');

        Notification::make()
            ->title('Monetization settings updated successfully')
            ->success()
            ->send();
    }
}
