<?php

namespace App\Services\Payments;

use App\Contracts\PaymentProviderInterface;
use App\DTOs\PaymentInitiationResult;
use App\DTOs\PaymentVerificationResult;
use App\DTOs\WebhookParseResult;
use App\Models\PaymentProvider;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlutterwaveProvider implements PaymentProviderInterface
{
    public function getCode(): string
    {
        return 'flutterwave';
    }

    public function getName(): string
    {
        return 'Flutterwave';
    }

    protected function getSecretKey(): ?string
    {
        $provider = PaymentProvider::where('code', 'flutterwave')->first();
        if ($provider && ! empty($provider->configuration['secret_key'])) {
            return $provider->configuration['secret_key'];
        }

        return config('services.flutterwave.secret_key', env('FLW_SECRET_KEY'));
    }

    protected function getWebhookSecret(): ?string
    {
        $provider = PaymentProvider::where('code', 'flutterwave')->first();
        if ($provider && ! empty($provider->webhook_secret)) {
            return $provider->webhook_secret;
        }

        return config('services.flutterwave.webhook_secret', env('FLW_WEBHOOK_SECRET'));
    }

    public function initiatePayment(PaymentTransaction $transaction, array $params = []): PaymentInitiationResult
    {
        $secretKey = $this->getSecretKey();

        if (empty($secretKey) || str_contains($secretKey, 'test_mock')) {
            return new PaymentInitiationResult(
                success: true,
                transactionReference: $transaction->public_reference,
                providerReference: 'FLW-MOCK-'.$transaction->id,
                checkoutUrl: "https://checkout.flutterwave.com/v3/hosted/pay/{$transaction->public_reference}",
                message: 'Flutterwave payment initialized successfully (mock mode).'
            );
        }

        try {
            $http = Http::timeout(15)->withToken($secretKey)->acceptJson();
            if (config('app.env') !== 'production') {
                $http = $http->withoutVerifying();
            }

            $response = $http->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $transaction->public_reference,
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
                'redirect_url' => $params['return_url'] ?? config('app.url'),
                'customer' => [
                    'email' => $transaction->user->email ?? "user_{$transaction->user_id}@lindr.app",
                    'phonenumber' => $params['phone_number'] ?? $transaction->user->phone_number ?? '0000000000',
                    'name' => $transaction->user->name ?? 'Lindr User',
                ],
                'customizations' => [
                    'title' => 'Lindr Coin Purchase',
                    'description' => "Purchase of {$transaction->expected_coins} Coins",
                ],
                'meta' => array_merge($transaction->metadata ?? [], [
                    'package_id' => $transaction->package_id,
                    'user_id' => $transaction->user_id,
                ]),
            ]);

            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json('data');

                return new PaymentInitiationResult(
                    success: true,
                    transactionReference: $transaction->public_reference,
                    providerReference: (string) ($data['id'] ?? $transaction->public_reference),
                    checkoutUrl: $data['link'] ?? null,
                    actionData: [
                        'checkout_url' => $data['link'] ?? null,
                        'flw_ref' => $data['id'] ?? null,
                    ],
                    message: 'Flutterwave payment link generated.',
                    rawResponse: $response->json()
                );
            }

            return new PaymentInitiationResult(
                success: false,
                transactionReference: $transaction->public_reference,
                message: $response->json('message') ?? 'Failed to initialize Flutterwave payment.',
                rawResponse: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('Flutterwave initiation error', ['error' => $e->getMessage()]);

            return new PaymentInitiationResult(
                success: false,
                transactionReference: $transaction->public_reference,
                message: 'Flutterwave service connection error: '.$e->getMessage()
            );
        }
    }

    public function verifyPayment(PaymentTransaction $transaction): PaymentVerificationResult
    {
        $secretKey = $this->getSecretKey();

        if (empty($secretKey) || str_contains($secretKey, 'test_mock')) {
            return new PaymentVerificationResult(
                success: true,
                status: 'successful',
                providerReference: 'FLW-MOCK-'.$transaction->id,
                receiptNumber: 'FLW-REC-'.rand(10000, 99999),
                message: 'Payment verified (mock mode).'
            );
        }

        try {
            $transactionId = $transaction->provider_reference ?? $transaction->public_reference;
            $http = Http::timeout(15)->withToken($secretKey)->acceptJson();
            if (config('app.env') !== 'production') {
                $http = $http->withoutVerifying();
            }

            $response = $http->get("https://api.flutterwave.com/v3/transactions/{$transactionId}/verify");

            if ($response->successful() && $response->json('status') === 'success') {
                $data = $response->json('data');
                $status = strtolower($data['status'] ?? 'pending');

                return new PaymentVerificationResult(
                    success: $status === 'successful',
                    status: $status,
                    providerReference: (string) ($data['id'] ?? $transactionId),
                    receiptNumber: $data['flw_ref'] ?? null,
                    message: $response->json('message') ?? 'Verification successful.',
                    rawResponse: $response->json()
                );
            }

            return new PaymentVerificationResult(
                success: false,
                status: 'failed',
                message: $response->json('message') ?? 'Flutterwave verification failed.',
                rawResponse: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('Flutterwave verification error', ['error' => $e->getMessage()]);

            return new PaymentVerificationResult(
                success: false,
                status: 'failed',
                message: $e->getMessage()
            );
        }
    }

    public function parseWebhook(array $payload, array $headers = []): WebhookParseResult
    {
        $webhookSecret = $this->getWebhookSecret();
        $hashHeader = $headers['verif-hash'] ?? $headers['Verif-Hash'] ?? null;

        $isValid = true;
        if (! empty($webhookSecret) && ! empty($hashHeader)) {
            $isValid = hash_equals($webhookSecret, (string) $hashHeader);
        }

        $event = $payload['event'] ?? 'charge.completed';
        $data = $payload['data'] ?? [];
        $txRef = $data['tx_ref'] ?? null;
        $status = strtolower($data['status'] ?? '');

        $normalizedStatus = match ($status) {
            'successful' => 'successful',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            default => 'pending',
        };

        return new WebhookParseResult(
            isValid: $isValid,
            event: $event,
            transactionReference: $txRef,
            providerReference: isset($data['id']) ? (string) $data['id'] : null,
            status: $normalizedStatus,
            receiptNumber: $data['flw_ref'] ?? null,
            payload: $payload
        );
    }

    public function checkHealth(): bool
    {
        return ! empty($this->getSecretKey());
    }
}
