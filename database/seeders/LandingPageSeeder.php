<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\LandingMedia;
use App\Models\LandingSetting;
use Illuminate\Database\Seeder;

class LandingPageSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'site_title', 'value' => 'Lindr — Meet Someone Worth Talking To', 'label' => 'Site Title', 'group' => 'seo'],
            ['key' => 'hero_headline', 'value' => 'Meet Someone Worth Talking To.', 'label' => 'Hero Headline', 'group' => 'hero'],
            ['key' => 'hero_description', 'value' => 'Lindr is a modern social discovery and dating platform. Connect naturally, match with real adults, send virtual gifts, and build genuine conversations.', 'label' => 'Hero Description', 'group' => 'hero'],
            ['key' => 'hero_cta_text', 'value' => 'Download Lindr', 'label' => 'Hero CTA Label', 'group' => 'hero'],
            ['key' => 'google_play_url', 'value' => 'https://play.google.com/store/apps/details?id=app.lindr.mobile', 'label' => 'Google Play URL', 'group' => 'app_links'],
            ['key' => 'apple_store_url', 'value' => 'https://apps.apple.com/app/lindr-dating/id123456789', 'label' => 'Apple App Store URL', 'group' => 'app_links'],
            ['key' => 'support_email', 'value' => 'support@lindr.app', 'label' => 'Support Email', 'group' => 'contact'],
            ['key' => 'contact_email', 'value' => 'hello@lindr.app', 'label' => 'General Contact Email', 'group' => 'contact'],
            ['key' => 'footer_description', 'value' => 'Lindr is a modern social discovery and dating platform designed for genuine adult connections.', 'label' => 'Footer Description', 'group' => 'footer'],
            ['key' => 'meta_title', 'value' => 'Lindr — Modern Social Discovery & Dating Platform', 'label' => 'Meta Title', 'group' => 'seo'],
            ['key' => 'meta_description', 'value' => 'Discover profiles, match naturally, chat privately, and build genuine adult connections on Lindr.', 'label' => 'Meta Description', 'group' => 'seo'],
            ['key' => 'meta_keywords', 'value' => 'dating app, social discovery, match, chat, coins, virtual gifts, Nairobi dating, online dating', 'label' => 'Meta Keywords', 'group' => 'seo'],
            ['key' => 'instagram_url', 'value' => 'https://instagram.com/lindrapp', 'label' => 'Instagram URL', 'group' => 'social'],
            ['key' => 'twitter_url', 'value' => 'https://x.com/lindrapp', 'label' => 'Twitter/X URL', 'group' => 'social'],
            ['key' => 'facebook_url', 'value' => 'https://facebook.com/lindrapp', 'label' => 'Facebook URL', 'group' => 'social'],
        ];

        foreach ($settings as $setting) {
            LandingSetting::set($setting['key'], $setting['value'], $setting['label'], $setting['group']);
        }

        $mediaAssets = [
            [
                'key' => 'hero_main',
                'title' => 'Hero Lifestyle Photo',
                'description' => 'Main lifestyle hero image',
                'file_path' => 'landing/hero_lifestyle.png',
                'alt_text' => 'Adult couple laughing together while using smartphone',
                'section' => 'hero',
                'sort_order' => 1,
            ],
            [
                'key' => 'hero_phone',
                'title' => 'Hero Phone Mockup',
                'description' => 'Smartphone mockup for hero section',
                'file_path' => 'landing/hero_phone_mockup.png',
                'alt_text' => 'Lindr App Discovery Interface Mockup',
                'section' => 'hero',
                'sort_order' => 2,
            ],
            [
                'key' => 'discover_screen',
                'title' => 'Discover Screen Mockup',
                'description' => 'Discovery feed mockup',
                'file_path' => 'landing/discover_screen.png',
                'alt_text' => 'Lindr Discovery Feed UI',
                'section' => 'mobile_screens',
                'sort_order' => 3,
            ],
            [
                'key' => 'match_screen',
                'title' => 'Match Screen Mockup',
                'description' => 'Match celebration screen mockup',
                'file_path' => 'landing/match_screen.png',
                'alt_text' => 'Lindr Match Screen UI',
                'section' => 'mobile_screens',
                'sort_order' => 4,
            ],
            [
                'key' => 'chat_screen',
                'title' => 'Chat Screen Mockup',
                'description' => 'Chat conversation mockup',
                'file_path' => 'landing/chat_screen.png',
                'alt_text' => 'Lindr Chat Interface UI',
                'section' => 'mobile_screens',
                'sort_order' => 5,
            ],
            [
                'key' => 'wallet_screen',
                'title' => 'Wallet Screen Mockup',
                'description' => 'Wallet & Coins screen mockup',
                'file_path' => 'landing/wallet_screen.png',
                'alt_text' => 'Lindr Wallet Interface UI',
                'section' => 'mobile_screens',
                'sort_order' => 6,
            ],
            [
                'key' => 'gifts_screen',
                'title' => 'Virtual Gifts Catalog Visual',
                'description' => 'Virtual gifts graphic',
                'file_path' => 'landing/gifts_screen.png',
                'alt_text' => 'Lindr Virtual Gifts Catalog',
                'section' => 'feature',
                'sort_order' => 7,
            ],
            [
                'key' => 'safety_image',
                'title' => 'Safety & Security Visual',
                'description' => 'Account safety graphic',
                'file_path' => 'landing/safety_illustration.png',
                'alt_text' => 'Lindr Account Safety and Encryption',
                'section' => 'safety',
                'sort_order' => 8,
            ],
            [
                'key' => 'download_image',
                'title' => 'Download App Lineup Mockup',
                'description' => 'Mobile screens lineup graphic',
                'file_path' => 'landing/download_app_mockup.png',
                'alt_text' => 'Lindr Smartphone App Showcase',
                'section' => 'cta',
                'sort_order' => 9,
            ],
            [
                'key' => 'final_cta_image',
                'title' => 'Final CTA Lifestyle Image',
                'description' => 'Romantic walking couple lifestyle photo',
                'file_path' => 'landing/final_cta_lifestyle.png',
                'alt_text' => 'Couple walking together at sunset',
                'section' => 'cta',
                'sort_order' => 10,
            ],
        ];

        foreach ($mediaAssets as $media) {
            LandingMedia::updateOrCreate(['key' => $media['key']], $media);
        }

        $faqs = [
            [
                'question' => 'What is Lindr?',
                'answer' => 'Lindr is a modern social discovery and dating platform that lets you explore profiles, match with real adult users, and chat securely.',
                'display_order' => 1,
            ],
            [
                'question' => 'How does matching work on Lindr?',
                'answer' => "When two users mutual like each other's profiles, a Match is instantly created so both users can start chatting privately.",
                'display_order' => 2,
            ],
            [
                'question' => 'Is Lindr available on mobile?',
                'answer' => 'Yes! Lindr is designed mobile-first. You can download the mobile application for iOS and Android.',
                'display_order' => 3,
            ],
            [
                'question' => 'How do Lindr Coins work?',
                'answer' => 'Lindr Coins unlock premium interactions such as sending virtual gifts or initiating paid messages for male accounts. Coins can be purchased safely via M-Pesa, KoraPay, Flutterwave, or Google Pay.',
                'display_order' => 4,
            ],
            [
                'question' => 'What payment methods are supported?',
                'answer' => 'Lindr supports M-Pesa STK Push, credit/debit cards via Flutterwave and KoraPay, and Google Pay depending on your location.',
                'display_order' => 5,
            ],
            [
                'question' => 'Can I block or report inappropriate users?',
                'answer' => 'Absolutely. Safety is our top priority. You can instantly block or report any user from their profile or chat screen. Blocked users are immediately hidden from your discovery feed.',
                'display_order' => 6,
            ],
            [
                'question' => 'How do I protect my account?',
                'answer' => 'Never share your password or OTP tokens. Lindr uses encrypted Sanctum token authentication and secure SSL server endpoints to keep your data safe.',
                'display_order' => 7,
            ],
            [
                'question' => 'How do I contact Lindr support?',
                'answer' => 'You can reach out to our dedicated support team anytime by emailing support@lindr.app.',
                'display_order' => 8,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }
    }
}
