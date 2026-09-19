<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\CoinPackage;
use App\Models\Gift;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\UserPhoto;
use App\Models\UserProfile;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Seed Platform Pricing & Configuration Settings
        PlatformSetting::set('message_cost', '5', 'Token cost per sent chat message to a verified creator');
        PlatformSetting::set('credits_per_usd', '10.0', 'Creator credits required per $1.00 USD payout value');
        PlatformSetting::set('minimum_withdrawal_credits', '100', 'Minimum withdrawal threshold in creator credits');
        PlatformSetting::set('payout_number_change_hold_hours', '48', 'Payout hold period in hours after updating M-Pesa phone number');
        PlatformSetting::set('matching_token_cost', '50', 'Token cost for instant profile match unlock (100% to Lindr)');
        PlatformSetting::set('audio_call_rate_per_minute', '20', 'Audio call token rate per minute');
        PlatformSetting::set('video_call_rate_per_minute', '30', 'Video call token rate per minute');

        // Revenue splits per gender
        PlatformSetting::set('chat_female_creator_share_pct', '60.0', 'Percentage of chat token revenue awarded to female creator');
        PlatformSetting::set('chat_male_creator_share_pct', '60.0', 'Percentage of chat token revenue awarded to male creator');
        PlatformSetting::set('call_female_creator_share_pct', '70.0', 'Percentage of call token revenue awarded to female creator');
        PlatformSetting::set('call_male_creator_share_pct', '70.0', 'Percentage of call token revenue awarded to male creator');
        PlatformSetting::set('gift_female_creator_share_pct', '60.0', 'Percentage of gift token revenue awarded to female creator');
        PlatformSetting::set('gift_male_creator_share_pct', '60.0', 'Percentage of gift token revenue awarded to male creator');

        // 2. Seed Default Coin Packages
        $packages = [
            ['name' => '100 Coins Pack', 'coin_amount' => 100, 'bonus_coins' => 0, 'price_kes' => 100.00, 'display_order' => 1],
            ['name' => '250 Coins Pack', 'coin_amount' => 250, 'bonus_coins' => 25, 'price_kes' => 250.00, 'display_order' => 2],
            ['name' => '500 Coins Pack', 'coin_amount' => 500, 'bonus_coins' => 75, 'price_kes' => 500.00, 'display_order' => 3],
            ['name' => '1000 Coins Pack', 'coin_amount' => 1000, 'bonus_coins' => 200, 'price_kes' => 1000.00, 'display_order' => 4],
            ['name' => '2500 Coins Pack', 'coin_amount' => 2500, 'bonus_coins' => 600, 'price_kes' => 2500.00, 'display_order' => 5],
            ['name' => '5000 Coins Pack', 'coin_amount' => 5000, 'bonus_coins' => 1500, 'price_kes' => 5000.00, 'display_order' => 6],
        ];

        foreach ($packages as $pkg) {
            CoinPackage::updateOrCreate(['name' => $pkg['name']], $pkg);
        }

        // 3. Seed Default Virtual Gifts
        $gifts = [
            ['name' => 'Red Rose', 'coin_price' => 20, 'recipient_share_percentage' => 60.0, 'image_url' => 'gifts/rose.png'],
            ['name' => 'Love Heart', 'coin_price' => 100, 'recipient_share_percentage' => 60.0, 'image_url' => 'gifts/heart.png'],
            ['name' => 'Diamond Ring', 'coin_price' => 500, 'recipient_share_percentage' => 60.0, 'image_url' => 'gifts/diamond.png'],
            ['name' => 'Golden Crown', 'coin_price' => 1000, 'recipient_share_percentage' => 60.0, 'image_url' => 'gifts/crown.png'],
        ];

        foreach ($gifts as $g) {
            Gift::updateOrCreate(['name' => $g['name']], $g);
        }

        // 4. Seed Administrator Account
        $admin = User::updateOrCreate(
            ['email' => 'admin@lindr.app'],
            [
                'name' => 'Lindr Administrator',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        Wallet::firstOrCreate(['user_id' => $admin->id], ['coin_balance' => 0]);

        // 5. Seed Male Demo User
        $male = User::updateOrCreate(
            ['email' => 'male@lindr.app'],
            [
                'name' => 'Alex Johnson',
                'phone' => '254711111111',
                'password' => Hash::make('password'),
                'role' => UserRole::Male,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        Wallet::updateOrCreate(['user_id' => $male->id], ['coin_balance' => 500]);
        UserProfile::updateOrCreate(
            ['user_id' => $male->id],
            [
                'display_name' => 'Alex',
                'date_of_birth' => '1996-04-12',
                'gender' => 'male',
                'bio' => 'Software Engineer in Nairobi. Enthusiastic about travel, music, and fitness.',
                'city' => 'Nairobi',
                'country' => 'Kenya',
                'interests' => ['Tech', 'Music', 'Fitness'],
                'online_status' => 'online',
                'is_verified' => true,
            ]
        );
        UserPhoto::updateOrCreate(['user_id' => $male->id, 'display_order' => 1], ['photo_path' => 'photos/male_demo.jpg', 'is_primary' => true]);

        // 6. Seed Female Demo User
        $female = User::updateOrCreate(
            ['email' => 'female@lindr.app'],
            [
                'name' => 'Sarah Wambui',
                'phone' => '254722222222',
                'password' => Hash::make('password'),
                'role' => UserRole::Female,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]
        );
        Wallet::updateOrCreate(['user_id' => $female->id], ['coin_balance' => 0]);
        UserProfile::updateOrCreate(
            ['user_id' => $female->id],
            [
                'display_name' => 'Sarah',
                'date_of_birth' => '1998-08-22',
                'gender' => 'female',
                'bio' => 'Designer & Content Creator based in Nairobi. Coffee lover and foodie.',
                'city' => 'Nairobi',
                'country' => 'Kenya',
                'interests' => ['Art', 'Food', 'Travel'],
                'online_status' => 'online',
                'is_verified' => true,
            ]
        );
        UserPhoto::updateOrCreate(['user_id' => $female->id, 'display_order' => 1], ['photo_path' => 'photos/female_demo.jpg', 'is_primary' => true]);

        // 7. Seed Payment Providers & Methods
        $this->call(PaymentGatewaySeeder::class);

        // 8. Seed Landing Page CMS Settings, Media & FAQs
        $this->call(LandingPageSeeder::class);
    }
}
