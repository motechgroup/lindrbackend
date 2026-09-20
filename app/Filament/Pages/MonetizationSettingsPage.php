<?php

namespace App\Filament\Pages;

use App\Models\PlatformSetting;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class MonetizationSettingsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsVertical;

    protected static UnitEnum|string|null $navigationGroup = 'Financials';

    protected static ?string $navigationLabel = 'Monetization & Rates';

    protected static ?string $title = 'Monetization & Commission Settings';

    protected string $view = 'filament.pages.monetization-settings';

    // Call Share Percentages
    public float $call_female_creator_share_pct = 70.0;

    public float $call_male_creator_share_pct = 70.0;

    // Chat Share Percentages
    public float $chat_female_creator_share_pct = 60.0;

    public float $chat_male_creator_share_pct = 60.0;

    // Gift Share Percentages
    public float $gift_female_creator_share_pct = 60.0;

    public float $gift_male_creator_share_pct = 60.0;

    // Rates & Costs
    public int $video_call_rate_per_minute = 30;

    public int $audio_call_rate_per_minute = 20;

    public int $message_cost = 5;

    public int $matching_token_cost = 50;

    // Withdrawal Settings
    public float $credits_per_usd = 10.0;

    public int $minimum_withdrawal_credits = 100;

    public int $payout_number_change_hold_hours = 48;

    public function mount(): void
    {
        $this->call_female_creator_share_pct = (float) PlatformSetting::get('call_female_creator_share_pct', 70.0);
        $this->call_male_creator_share_pct = (float) PlatformSetting::get('call_male_creator_share_pct', 70.0);

        $this->chat_female_creator_share_pct = (float) PlatformSetting::get('chat_female_creator_share_pct', 60.0);
        $this->chat_male_creator_share_pct = (float) PlatformSetting::get('chat_male_creator_share_pct', 60.0);

        $this->gift_female_creator_share_pct = (float) PlatformSetting::get('gift_female_creator_share_pct', 60.0);
        $this->gift_male_creator_share_pct = (float) PlatformSetting::get('gift_male_creator_share_pct', 60.0);

        $this->video_call_rate_per_minute = (int) PlatformSetting::get('video_call_rate_per_minute', 30);
        $this->audio_call_rate_per_minute = (int) PlatformSetting::get('audio_call_rate_per_minute', 20);
        $this->message_cost = (int) PlatformSetting::get('message_cost', PlatformSetting::get('chat_coins', 5));
        $this->matching_token_cost = (int) PlatformSetting::get('matching_token_cost', PlatformSetting::get('match_coins', 50));

        $this->credits_per_usd = (float) PlatformSetting::get('credits_per_usd', 10.0);
        $this->minimum_withdrawal_credits = (int) PlatformSetting::get('minimum_withdrawal_credits', 100);
        $this->payout_number_change_hold_hours = (int) PlatformSetting::get('payout_number_change_hold_hours', 48);
    }

    public function save(): void
    {
        PlatformSetting::set('call_female_creator_share_pct', (string) $this->call_female_creator_share_pct, 'Percentage of call token revenue awarded to female creator');
        PlatformSetting::set('call_male_creator_share_pct', (string) $this->call_male_creator_share_pct, 'Percentage of call token revenue awarded to male creator');

        PlatformSetting::set('chat_female_creator_share_pct', (string) $this->chat_female_creator_share_pct, 'Percentage of chat token revenue awarded to female creator');
        PlatformSetting::set('chat_male_creator_share_pct', (string) $this->chat_male_creator_share_pct, 'Percentage of chat token revenue awarded to male creator');

        PlatformSetting::set('gift_female_creator_share_pct', (string) $this->gift_female_creator_share_pct, 'Percentage of gift token revenue awarded to female creator');
        PlatformSetting::set('gift_male_creator_share_pct', (string) $this->gift_male_creator_share_pct, 'Percentage of gift token revenue awarded to male creator');

        PlatformSetting::set('video_call_rate_per_minute', (string) $this->video_call_rate_per_minute, 'Video call token rate per minute');
        PlatformSetting::set('audio_call_rate_per_minute', (string) $this->audio_call_rate_per_minute, 'Audio call token rate per minute');

        // Chat Coins / Message Cost
        PlatformSetting::set('message_cost', (string) $this->message_cost, 'Token cost per sent chat message to a verified creator');
        PlatformSetting::set('chat_coins', (string) $this->message_cost, 'Chat coins cost per sent chat message');

        // Match Coins / Instant Match Cost
        PlatformSetting::set('matching_token_cost', (string) $this->matching_token_cost, 'Token cost for instant profile match unlock');
        PlatformSetting::set('match_coins', (string) $this->matching_token_cost, 'Match coins cost per instant match');

        PlatformSetting::set('credits_per_usd', (string) $this->credits_per_usd, 'Creator credits required per $1.00 USD payout value');
        PlatformSetting::set('minimum_withdrawal_credits', (string) $this->minimum_withdrawal_credits, 'Minimum withdrawal threshold in creator credits');
        PlatformSetting::set('payout_number_change_hold_hours', (string) $this->payout_number_change_hold_hours, 'Payout hold period in hours after updating M-Pesa phone number');

        Notification::make()
            ->title('Monetization settings updated successfully')
            ->success()
            ->send();
    }
}
