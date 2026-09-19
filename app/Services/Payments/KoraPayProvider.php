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

class KoraPayProvider implements PaymentProviderInterface
{
    public function getCode(): string
    {
        return 'korapay';
    }

    public function getName(): string
    {
        return 'KoraPay';
    }

    protected function getSecretKey(): ?string
    {
        $provider = PaymentProvider::where('code', 'korapay')->first();
        if ($provider && ! empty($provider->configuration['secret_key'])) {
            return $provider->configuration['secret_key'];
        }

        return config('services.korapay.secret_key', env('KORAPAY_SECRET_KEY'));
    }

    protected function getWebhookSecret(): ?string
    {
        $provider = PaymentProvider::where('code', 'korapay')->first();
        if ($provider && ! empty($provider->webhook_secret)) {
            return $provider->webhook_secret;
        }

        return config('services.korapay.webhook_secret', env('KORAPAY_WEBHOOK_SECRET'));
    }

    public function initiatePayment(PaymentTransaction $transaction, array $params = []): PaymentInitiationResult
    {
        $secretKey = $this->getSecretKey();

        // If credentials are missing in dev/test, return mock checkout response
        if (empty($secretKey) || str_contains($secretKey, 'test_mock')) {
            return new PaymentInitiationResult(
                success: true,
                transactionReference: $transaction->public_reference,
                providerReference: 'KORA-MOCK-'.$transaction->id,
                checkoutUrl: "https://checkout.korapay.com/pay/{$transaction->public_reference}",
                message: 'KoraPay checkout initialized successfully (mock mode).'
            );
        }

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->post('https://api.korapay.com/merchant/api/v1/charges/initialize', [
                    'reference' => $transaction->public_reference,
                    'amount' => $transaction->amount,
                    'currency' => $transaction->currency,
                    'customer' => [
                        'email' => $transaction->user->email ?? "user_{$transaction->user_id}@lindr.app",
                        'name' => $transaction->user->name ?? 'Lindr User',
                    ],
                    'notification_url' => route('webhooks.kora'),
                    'redirect_url' => $params['return_url'] ?? config('app.url'),
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'package_id' => $transaction->package_id,
                        'user_id' => $transaction->user_id,
                    ]),
                ]);

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data');

                return new PaymentInitiationResult(
                    success: true,
                    transactionReference: $transaction->public_reference,
                    providerReference: $data['reference'] ?? null,
                    checkoutUrl: $data['checkout_url'] ?? null,
                    message: 'KoraPay payment initialized successfully.',
                    rawResponse: $response->json()
                );
            }

            return new PaymentInitiationResult(
                success: false,
                transactionReference: $transaction->public_reference,
                message: $response->json('message') ?? 'Failed to initialize KoraPay payment.',
                rawResponse: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('KoraPay initiation error', ['error' => $e->getMessage()]);

            return new PaymentInitiationResult(
                success: false,
                transactionReference: $transaction->public_reference,
                message: 'KoraPay service connection error: '.$e->getMessage()
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
                providerReference: 'KORA-MOCK-'.$transaction->id,
                receiptNumber: 'KORA-REC-'.rand(10000, 99999),
                message: 'Payment verified (mock mode).'
            );
        }

        try {
            $response = Http::withToken($secretKey)
                ->acceptJson()
                ->get("https://api.korapay.com/merchant/api/v1/charges/{$transaction->public_reference}");

            if ($response->successful() && $response->json('status') === true) {
                $data = $response->json('data');
                $status = strtolower($data['status'] ?? 'pending');

                return new PaymentVerificationResult(
                    success: $status === 'success' || $status === 'successful',
                    status: $status === 'success' ? 'successful' : $status,
                    providerReference: $data['reference'] ?? null,
                    receiptNumber: $data['transaction_reference'] ?? null,
                    message: $response->json('message') ?? 'Payment status fetched.',
                    rawResponse: $response->json()
                );
            }

            return new PaymentVerificationResult(
                success: false,
                status: 'failed',
                message: $response->json('message') ?? 'Verification request failed.',
                rawResponse: $response->json() ?? []
            );
        } catch (\Throwable $e) {
            Log::error('KoraPay verification error', ['error' => $e->getMessage()]);

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
        $signature = $headers['x-korapay-signature'] ?? $headers['X-Korapay-Signature'] ?? null;

        $isValid = true;
        if (! empty($webhookSecret) && ! empty($signature) && is_string($signature)) {
            $computedSignature = hash_hmac('sha512', json_encode($payload), $webhookSecret);
            $isValid = hash_equals($computedSignature, $signature);
        }

        $event = $payload['event'] ?? null;
        $data = $payload['data'] ?? [];
        $reference = $data['reference'] ?? null;
        $status = strtolower($data['status'] ?? '');

        $normalizedStatus = match ($status) {
            'success', 'successful' => 'successful',
            'failed' => 'failed',
            'expired' => 'expired',
            default => 'pending',
        };

        return new WebhookParseResult(
            isValid: $isValid,
            event: $event,
            transactionReference: $reference,
            providerReference: $data['transaction_reference'] ?? null,
            status: $normalizedStatus,
            receiptNumber: $data['transaction_reference'] ?? null,
            payload: $payload
        );
    }

    public function checkHealth(): bool
    {
        return ! empty($this->getSecretKey());
    }
}
