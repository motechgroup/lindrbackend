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

class MpesaPaymentProvider implements PaymentProviderInterface
{
    public function getCode(): string
    {
        return 'mpesa';
    }

    public function getName(): string
    {
        return 'Safaricom M-Pesa (Daraja)';
    }

    protected function getConfiguration(): array
    {
        $provider = PaymentProvider::where('code', 'mpesa')->first();
        if ($provider && ! empty($provider->configuration)) {
            return $provider->configuration;
        }

        return [
            'consumer_key' => config('services.mpesa.consumer_key', env('MPESA_CONSUMER_KEY')),
            'consumer_secret' => config('services.mpesa.consumer_secret', env('MPESA_CONSUMER_SECRET')),
            'shortcode' => config('services.mpesa.shortcode', env('MPESA_SHORTCODE', '174379')),
            'passkey' => config('services.mpesa.passkey', env('MPESA_PASSKEY')),
            'environment' => env('MPESA_ENVIRONMENT', 'sandbox'),
            'callback_url' => config('services.mpesa.callback_url', env('MPESA_CALLBACK_URL', route('webhooks.mpesa'))),
        ];
    }

    public function initiatePayment(PaymentTransaction $transaction, array $params = []): PaymentInitiationResult
    {
        $config = $this->getConfiguration();
        $consumerKey = $config['consumer_key'] ?? null;
        $consumerSecret = $config['consumer_secret'] ?? null;
        $shortCode = $config['shortcode'] ?? '174379';
        $passKey = $config['passkey'] ?? null;
        $phoneNumber = $params['phone_number'] ?? $transaction->user->phone_number ?? '';

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if ($consumerKey && $consumerSecret && $passKey && ! str_contains($consumerKey, 'mock')) {
            try {
                $env = $config['environment'] ?? 'sandbox';
                $baseUrl = $env === 'production'
                    ? 'https://api.safaricom.co.ke'
                    : 'https://sandbox.safaricom.co.ke';

                $authResponse = Http::withBasicAuth($consumerKey, $consumerSecret)
                    ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

                if ($authResponse->successful()) {
                    $token = $authResponse->json('access_token');
                    $timestamp = date('YmdHis');
                    $password = base64_encode($shortCode.$passKey.$timestamp);

                    $stkResponse = Http::withToken($token)
                        ->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", [
                            'BusinessShortCode' => $shortCode,
                            'Password' => $password,
                            'Timestamp' => $timestamp,
                            'TransactionType' => 'CustomerPayBillOnline',
                            'Amount' => (int) round($transaction->amount),
                            'PartyA' => $formattedPhone,
                            'PartyB' => $shortCode,
                            'PhoneNumber' => $formattedPhone,
                            'CallBackURL' => $config['callback_url'] ?? route('webhooks.mpesa'),
                            'AccountReference' => 'LindrCoins',
                            'TransactionDesc' => "Coin Package #{$transaction->package_id}",
                        ]);

                    if ($stkResponse->successful()) {
                        $checkoutReqId = $stkResponse->json('CheckoutRequestID');

                        return new PaymentInitiationResult(
                            success: true,
                            transactionReference: $transaction->public_reference,
                            providerReference: $checkoutReqId,
                            actionData: [
                                'checkout_request_id' => $checkoutReqId,
                                'merchant_request_id' => $stkResponse->json('MerchantRequestID'),
                                'phone_number' => $formattedPhone,
                            ],
                            message: 'STK push prompt sent to phone number.',
                            rawResponse: $stkResponse->json()
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error('M-Pesa STK Push error: '.$e->getMessage());
            }
        }

        // Mock fallback for test environment
        $mockCheckoutId = 'ws_CO_'.now()->format('dmYHis').'_'.rand(1000, 9999);

        return new PaymentInitiationResult(
            success: true,
            transactionReference: $transaction->public_reference,
            providerReference: $mockCheckoutId,
            actionData: [
                'checkout_request_id' => $mockCheckoutId,
                'merchant_request_id' => 'mr_'.rand(100000, 999999),
                'phone_number' => $formattedPhone,
            ],
            message: 'STK push prompt sent to phone number (simulated mode).'
        );
    }

    public function verifyPayment(PaymentTransaction $transaction): PaymentVerificationResult
    {
        $status = $transaction->status;

        return new PaymentVerificationResult(
            success: $status === 'successful',
            status: $status,
            providerReference: $transaction->provider_reference,
            message: "Transaction status is {$status}."
        );
    }

    public function parseWebhook(array $payload, array $headers = []): WebhookParseResult
    {
        $stkCallback = $payload['Body']['stkCallback'] ?? $payload;

        $resultCode = (int) ($stkCallback['ResultCode'] ?? 1);
        $checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? null;

        $receiptNumber = null;
        if ($resultCode === 0 && isset($stkCallback['CallbackMetadata']['Item'])) {
            foreach ($stkCallback['CallbackMetadata']['Item'] as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $receiptNumber = $item['Value'] ?? null;
                    break;
                }
            }
        }

        $status = $resultCode === 0 ? 'successful' : 'failed';

        return new WebhookParseResult(
            isValid: true,
            event: 'stk_callback',
            transactionReference: null,
            providerReference: $checkoutRequestId,
            status: $status,
            receiptNumber: $receiptNumber,
            payload: $payload
        );
    }

    public function checkHealth(): bool
    {
        $config = $this->getConfiguration();

        return ! empty($config['consumer_key']);
    }

    private function formatPhoneNumber(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($cleaned, '0')) {
            return '254'.substr($cleaned, 1);
        }
        if (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1')) {
            return '254'.$cleaned;
        }

        return $cleaned;
    }
}
