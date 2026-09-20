<?php

use App\Services\CallService;
use App\Services\Payments\MpesaPaymentProvider;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('calls:reconcile', function () {
    $count = app(CallService::class)->reconcileStaleSessions();
    $this->info("Reconciled {$count} stale call session(s).");
})->purpose('Reconcile abandoned or hanging call sessions')->everyMinute();

Artisan::command('deploy:refresh', function () {
    $this->info('Clearing all application, view, route, and config caches...');
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    $this->info('Deploy refresh complete! All compiled view, route, and OPcache layers purged.');
})->purpose('Purge all compiled views, routes, config caches, and OPcache on production server');

Artisan::command('mpesa:test', function () {
    $this->info('==================================================');
    $this->info('  SAFARICOM DARAJA M-PESA CREDENTIAL DIAGNOSTICS  ');
    $this->info('==================================================');

    /** @var MpesaPaymentProvider $provider */
    $provider = app(MpesaPaymentProvider::class);

    $reflection = new ReflectionClass($provider);
    $method = $reflection->getMethod('getConfiguration');
    $method->setAccessible(true);
    $config = $method->invoke($provider);

    $env = $config['environment'] ?? 'sandbox';
    $key = trim($config['consumer_key'] ?? '');
    $secret = trim($config['consumer_secret'] ?? '');
    $shortcode = trim($config['shortcode'] ?? '');
    $passkey = trim($config['passkey'] ?? '');
    $callback = $config['callback_url'] ?? '';

    $this->line('• Environment:     '.strtoupper($env));
    $this->line('• Consumer Key:    '.(empty($key) ? '<fg=red>MISSING</>' : substr($key, 0, 6).'***'));
    $this->line('• Consumer Secret: '.(empty($secret) ? '<fg=red>MISSING</>' : substr($secret, 0, 6).'***'));
    $this->line('• ShortCode:       '.(empty($shortcode) ? '<fg=red>MISSING</>' : $shortcode));
    $this->line('• Passkey:         '.(empty($passkey) ? '<fg=red>MISSING</>' : substr($passkey, 0, 6).'***'));
    $this->line('• Callback URL:    '.($callback ?: '<fg=red>MISSING</>'));
    $this->newLine();

    if (empty($key) || empty($secret)) {
        $this->error('❌ FAILED: MPESA_CONSUMER_KEY or MPESA_CONSUMER_SECRET is not configured.');

        return 1;
    }

    $baseUrl = $env === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';

    $this->info("Connecting to Safaricom Daraja OAuth ({$baseUrl})...");

    try {
        $http = Http::timeout(10);
        if ($env !== 'production') {
            $http = $http->withoutVerifying();
        }

        $response = $http->withBasicAuth($key, $secret)
            ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

        if ($response->successful()) {
            $token = $response->json('access_token');
            $expires = $response->json('expires_in');

            $this->info('✅ SUCCESS: Safaricom Daraja OAuth authentication succeeded!');
            $this->info('   • Access Token: '.substr($token, 0, 15).'... (Expires in '.$expires.'s)');

            if (! empty($passkey) && ! empty($shortcode)) {
                $this->info('✅ STK Push Configuration: Ready for Live Push Prompts.');
            } else {
                $this->warn('⚠️ STK Push Warning: Passkey or ShortCode is incomplete.');
            }

            return 0;
        }

        $this->error('❌ FAILED: Safaricom Daraja API rejected credentials (HTTP '.$response->status().')');
        $this->error('   Response Body: '.$response->body());

        return 1;
    } catch (Throwable $e) {
        $this->error('❌ ERROR: Exception connecting to Safaricom API: '.$e->getMessage());

        return 1;
    }
})->purpose('Test Safaricom Daraja M-Pesa OAuth credentials and STK configuration');
