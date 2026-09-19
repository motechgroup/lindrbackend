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
            ['key' => 'site_title', 'value' => 'Lindr — Meet. Connect. Go Live.', 'label' => 'Site Title', 'group' => 'seo'],
            ['key' => 'hero_headline', 'value' => 'Meet. Connect. Go Live.', 'label' => 'Hero Headline', 'group' => 'hero'],
            ['key' => 'hero_description', 'value' => 'Discover real people, connect instantly and turn conversations into live video experiences.', 'label' => 'Hero Description', 'group' => 'hero'],
            ['key' => 'hero_cta_text', 'value' => 'Get Started', 'label' => 'Hero CTA Label', 'group' => 'hero'],
            ['key' => 'google_play_url', 'value' => 'https://play.google.com/store/apps/details?id=app.lindr.mobile', 'label' => 'Google Play URL', 'group' => 'app_links'],
            ['key' => 'apple_store_url', 'value' => 'https://apps.apple.com/app/lindr-dating/id123456789', 'label' => 'Apple App Store URL', 'group' => 'app_links'],
            ['key' => 'support_email', 'value' => 'support@lindr.app', 'label' => 'Support Email', 'group' => 'contact'],
            ['key' => 'contact_email', 'value' => 'hello@lindr.app', 'label' => 'General Contact Email', 'group' => 'contact'],
            ['key' => 'footer_description', 'value' => 'Lindr is a social discovery and live video connection platform where users can discover people, connect through Match or Discover, chat, send gifts, and have live video calls.', 'label' => 'Footer Description', 'group' => 'footer'],
            ['key' => 'meta_title', 'value' => 'Lindr — Meet. Connect. Go Live.', 'label' => 'Meta Title', 'group' => 'seo'],
            ['key' => 'meta_description', 'value' => 'Discover people, connect through Match and Discover, chat, send gifts and enjoy live video conversations on Lindr.', 'label' => 'Meta Description', 'group' => 'seo'],
            ['key' => 'meta_keywords', 'value' => 'social discovery, live video calls, match, discover people, virtual gifts, lindr app, tokens, creator earnings, m-pesa', 'label' => 'Meta Keywords', 'group' => 'seo'],
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
                'alt_text' => 'People laughing together while using smartphone',
                'section' => 'hero',
                'sort_order' => 1,
            ],
            [
                'key' => 'hero_phone',
                'title' => 'Hero Phone Mockup',
                'description' => 'Smartphone mockup for hero section',
                'file_path' => 'landing/hero_phone_mockup.png',
                'alt_text' => 'Lindr Mobile App Interface Mockup',
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
                'description' => 'Match connection screen mockup',
                'file_path' => 'landing/match_screen.png',
                'alt_text' => 'Lindr Match Connection UI',
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
                'description' => 'Tokens & Earnings screen mockup',
                'file_path' => 'landing/wallet_screen.png',
                'alt_text' => 'Lindr Tokens Wallet Interface UI',
                'section' => 'mobile_screens',
                'sort_order' => 6,
            ],
            [
                'key' => 'gifts_screen',
                'title' => 'Virtual Gifts Catalog Visual',
                'description' => 'Virtual gifts graphic',
                'file_path' => 'landing/gifts_screen.png',
                'alt_text' => 'Lindr Digital Gifts Catalog',
                'section' => 'feature',
                'sort_order' => 7,
            ],
            [
                'key' => 'safety_image',
                'title' => 'Safety & Security Visual',
                'description' => 'Account safety graphic',
                'file_path' => 'landing/safety_illustration.png',
                'alt_text' => 'Lindr Account Safety and Moderation',
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
                'description' => 'Lifestyle connection photo',
                'file_path' => 'landing/final_cta_lifestyle.png',
                'alt_text' => 'Friends connecting together',
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
                'answer' => 'Lindr is a social discovery and live video connection platform where users can discover people, connect through Match or Discover, chat, send digital gifts, and have live video calls.',
                'display_order' => 1,
            ],
            [
                'question' => 'How does Match work?',
                'answer' => 'Tap Match and let Lindr find an available person for you. When an eligible person accepts your connection request, you are matched instantly and can begin a live video conversation.',
                'display_order' => 2,
            ],
            [
                'question' => 'How does Discover work?',
                'answer' => 'Discover lets you browse active profiles, explore interests, check availability, and choose who you would like to connect with directly.',
                'display_order' => 3,
            ],
            [
                'question' => 'What are Lindr Tokens?',
                'answer' => 'Tokens are the platform spending currency used for initiating Match requests, video calls, sending digital gifts, and unlocking premium interactions.',
                'display_order' => 4,
            ],
            [
                'question' => 'How can creators earn?',
                'answer' => 'Verified creators earn Credits from eligible monetized interactions such as video calls and digital gifts received from other users.',
                'display_order' => 5,
            ],
            [
                'question' => 'Who can become a creator?',
                'answer' => 'Both verified women and verified men can complete creator verification and earn Credits on Lindr.',
                'display_order' => 6,
            ],
            [
                'question' => 'How does creator verification work?',
                'answer' => 'Creators complete Lindr\'s selfie and liveness verification before they can begin earning Credits.',
                'display_order' => 7,
            ],
            [
                'question' => 'How do creator withdrawals work?',
                'answer' => 'Verified creators can redeem eligible Credits through supported withdrawal methods, including M-Pesa in Kenya.',
                'display_order' => 8,
            ],
            [
                'question' => 'What is Lindr Spotlight?',
                'answer' => 'Spotlight allows you to boost your profile visibility across eligible discovery surfaces for a limited time to increase your exposure.',
                'display_order' => 9,
            ],
            [
                'question' => 'How does Lindr help keep users safe?',
                'answer' => 'Lindr uses creator liveness verification, 1-click blocking, reporting tools, and active moderation to foster a safer community environment.',
                'display_order' => 10,
            ],
        ];

        foreach ($faqs as $faq) {
            Faq::updateOrCreate(['question' => $faq['question']], $faq);
        }
    }
}
