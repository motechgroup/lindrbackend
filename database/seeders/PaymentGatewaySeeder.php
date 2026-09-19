<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use App\Models\PaymentProvider;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $providers = [
            [
                'code' => 'mpesa',
                'name' => 'Safaricom M-Pesa (Daraja)',
                'enabled' => true,
                'test_mode' => true,
                'priority' => 1,
                'supported_countries' => ['KE'],
                'supported_currencies' => ['KES'],
                'configuration' => [
                    'consumer_key' => env('MPESA_CONSUMER_KEY', ''),
                    'consumer_secret' => env('MPESA_CONSUMER_SECRET', ''),
                    'shortcode' => env('MPESA_SHORTCODE', '174379'),
                    'passkey' => env('MPESA_PASSKEY', ''),
                    'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
                    'callback_url' => env('MPESA_CALLBACK_URL', 'http://localhost:8000/api/v1/webhooks/mpesa'),
                ],
                'status' => 'active',
            ],
            [
                'code' => 'korapay',
                'name' => 'KoraPay',
                'enabled' => true,
                'test_mode' => true,
                'priority' => 2,
                'supported_countries' => ['KE', 'NG', 'GH'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD'],
                'configuration' => [
                    'public_key' => env('KORAPAY_PUBLIC_KEY', ''),
                    'secret_key' => env('KORAPAY_SECRET_KEY', ''),
                ],
                'webhook_secret' => env('KORAPAY_WEBHOOK_SECRET', ''),
                'status' => 'active',
            ],
            [
                'code' => 'flutterwave',
                'name' => 'Flutterwave',
                'enabled' => true,
                'test_mode' => true,
                'priority' => 3,
                'supported_countries' => ['KE', 'NG', 'GH', 'US', 'GB'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD', 'GBP'],
                'configuration' => [
                    'public_key' => env('FLW_PUBLIC_KEY', ''),
                    'secret_key' => env('FLW_SECRET_KEY', ''),
                ],
                'webhook_secret' => env('FLW_WEBHOOK_SECRET', ''),
                'status' => 'active',
            ],
            [
                'code' => 'google_pay',
                'name' => 'Google Pay',
                'enabled' => true,
                'test_mode' => true,
                'priority' => 4,
                'supported_countries' => ['KE', 'NG', 'GH', 'US', 'GB'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD', 'GBP'],
                'configuration' => [
                    'merchant_id' => env('GOOGLE_PAY_MERCHANT_ID', '12345678901234567890'),
                    'merchant_name' => env('GOOGLE_PAY_MERCHANT_NAME', 'Lindr Dating'),
                    'processor' => env('GOOGLE_PAY_PROCESSOR', 'flutterwave'),
                    'environment' => env('GOOGLE_PAY_ENVIRONMENT', 'TEST'),
                ],
                'status' => 'active',
            ],
        ];

        foreach ($providers as $provData) {
            PaymentProvider::updateOrCreate(['code' => $provData['code']], $provData);
        }

        $methods = [
            [
                'code' => 'mpesa',
                'name' => 'M-Pesa Express (STK Push)',
                'provider_code' => 'mpesa',
                'enabled' => true,
                'supported_countries' => ['KE'],
                'supported_currencies' => ['KES'],
                'minimum_amount' => 10.00,
                'maximum_amount' => 250000.00,
                'display_order' => 1,
            ],
            [
                'code' => 'korapay',
                'name' => 'KoraPay Checkout',
                'provider_code' => 'korapay',
                'enabled' => true,
                'supported_countries' => ['KE', 'NG', 'GH'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD'],
                'minimum_amount' => 10.00,
                'maximum_amount' => 500000.00,
                'display_order' => 2,
            ],
            [
                'code' => 'flutterwave',
                'name' => 'Flutterwave (Cards & Mobile Money)',
                'provider_code' => 'flutterwave',
                'enabled' => true,
                'supported_countries' => ['KE', 'NG', 'GH', 'US', 'GB'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD', 'GBP'],
                'minimum_amount' => 10.00,
                'maximum_amount' => 1000000.00,
                'display_order' => 3,
            ],
            [
                'code' => 'google_pay',
                'name' => 'Google Pay',
                'provider_code' => 'google_pay',
                'enabled' => true,
                'supported_countries' => ['KE', 'NG', 'GH', 'US', 'GB'],
                'supported_currencies' => ['KES', 'NGN', 'GHS', 'USD', 'GBP'],
                'minimum_amount' => 10.00,
                'maximum_amount' => 1000000.00,
                'display_order' => 4,
            ],
        ];

        foreach ($methods as $methData) {
            PaymentMethod::updateOrCreate(['code' => $methData['code']], $methData);
        }
    }
}
