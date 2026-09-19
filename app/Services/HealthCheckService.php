<?php

namespace App\Services;

use App\Models\PaymentProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthCheckService
{
    /**
     * Perform health diagnostics on system components safely without exposing secrets.
     *
     * @return array<string, mixed>
     */
    public function checkSystemHealth(): array
    {
        return [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'version' => '1.0.0',
            'app' => $this->checkApp(),
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'livekit' => $this->checkLiveKit(),
            'mpesa' => $this->checkMpesa(),
        ];
    }

    protected function checkApp(): array
    {
        return [
            'status' => 'ok',
            'environment' => config('app.env', 'production'),
            'debug_mode' => (bool) config('app.debug', false),
            'timezone' => config('app.timezone', 'UTC'),
        ];
    }

    protected function checkDatabase(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            DB::connection()->getPdo();

            return [
                'status' => 'ok',
                'driver' => $driver,
                'connected' => true,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'driver' => config('database.default', 'unknown'),
                'connected' => false,
                'error' => 'Database connection unavailable',
            ];
        }
    }

    protected function checkRedis(): array
    {
        $defaultDriver = config('cache.default', 'file');
        if (! class_exists('\Redis') && ! class_exists('\Predis\Client')) {
            return [
                'status' => 'disabled',
                'configured' => false,
                'message' => 'Redis PHP extension or Predis library not loaded in PHP runtime',
            ];
        }

        try {
            Redis::connection()->ping();

            return [
                'status' => 'ok',
                'configured' => true,
                'connected' => true,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'configured' => true,
                'connected' => false,
                'error' => 'Redis connection unavailable',
            ];
        }
    }

    protected function checkStorage(): array
    {
        try {
            $testFile = 'health_check_'.now()->timestamp.'.tmp';
            $path = storage_path('app/'.$testFile);

            file_put_contents($path, 'health_check');
            $writable = file_exists($path);
            if ($writable) {
                @unlink($path);
            }

            return [
                'status' => $writable ? 'ok' : 'failed',
                'writable' => $writable,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'writable' => false,
            ];
        }
    }

    protected function checkQueue(): array
    {
        $driver = config('queue.default', 'sync');

        return [
            'status' => 'ok',
            'default_driver' => $driver,
        ];
    }

    protected function checkLiveKit(): array
    {
        $url = config('services.livekit.host') ?? env('LIVEKIT_URL');
        $key = config('services.livekit.api_key') ?? env('LIVEKIT_API_KEY');
        $secret = config('services.livekit.api_secret') ?? env('LIVEKIT_API_SECRET');

        $isConfigured = ! empty($url) && ! empty($key) && ! empty($secret) && ! str_contains((string) $key, 'mock');

        return [
            'status' => $isConfigured ? 'ok' : 'mock_or_unconfigured',
            'configured' => $isConfigured,
        ];
    }

    protected function checkMpesa(): array
    {
        $config = config('services.mpesa', []);
        $dbProvider = PaymentProvider::where('code', 'mpesa')->first();
        if ($dbProvider && ! empty($dbProvider->configuration)) {
            $config = array_merge($config, $dbProvider->configuration);
        }

        $key = $config['consumer_key'] ?? null;
        $secret = $config['consumer_secret'] ?? null;
        $shortcode = $config['shortcode'] ?? null;

        $isConfigured = ! empty($key) && ! empty($secret) && ! empty($shortcode) && ! str_contains((string) $key, 'mock');

        return [
            'status' => $isConfigured ? 'ok' : 'sandbox_or_unconfigured',
            'configured' => $isConfigured,
            'environment' => $config['environment'] ?? 'sandbox',
        ];
    }
}
