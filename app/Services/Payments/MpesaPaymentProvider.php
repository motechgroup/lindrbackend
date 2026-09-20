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
        $config = config('services.mpesa', []);
        $provider = PaymentProvider::where('code', 'mpesa')->first();
        if ($provider && ! empty($provider->configuration)) {
            $dbConfig = array_filter($provider->configuration, fn ($v) => ! is_null($v) && $v !== '');
            $config = array_merge($config, $dbConfig);
        }

        $appUrl = env('APP_URL', 'http://localhost:8000');

        return [
            'consumer_key' => $config['consumer_key'] ?? null,
            'consumer_secret' => $config['consumer_secret'] ?? null,
            'shortcode' => $config['shortcode'] ?? '174379',
            'passkey' => $config['passkey'] ?? null,
            'environment' => $config['environment'] ?? 'sandbox',
            'callback_url' => $config['callback_url'] ?? (rtrim($appUrl, '/').'/api/v1/webhooks/mpesa'),
        ];
    }

    public function initiatePayment(PaymentTransaction $transaction, array $params = []): PaymentInitiationResult
    {
        $config = $this->getConfiguration();
        $consumerKey = trim($config['consumer_key'] ?? '');
        $consumerSecret = trim($config['consumer_secret'] ?? '');
        $shortCode = trim($config['shortcode'] ?? '174379');
        $passKey = trim($config['passkey'] ?? '');
        $phoneNumber = $params['phone_number'] ?? $transaction->user->phone_number ?? '';

        $formattedPhone = $this->formatPhoneNumber($phoneNumber);

        if (! empty($consumerKey) && ! empty($consumerSecret) && ! empty($passKey) && strtolower($consumerKey) !== 'mock') {
            try {
                $env = $config['environment'] ?? 'sandbox';
                $baseUrl = $env === 'production'
                    ? 'https://api.safaricom.co.ke'
                    : 'https://sandbox.safaricom.co.ke';

                $http = Http::timeout(10);
                if ($env !== 'production') {
                    $http = $http->withoutVerifying();
                }

                $authResponse = $http->withBasicAuth($consumerKey, $consumerSecret)
                    ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

                if (! $authResponse->successful()) {
                    $errText = $authResponse->json('errorMessage') ?? $authResponse->json('error_description') ?? $authResponse->body();
                    if (empty(trim((string) $errText))) {
                        $errText = "Invalid Consumer Key or Secret (HTTP {$authResponse->status()})";
                    }
                    Log::error('M-Pesa OAuth Auth failed', ['status' => $authResponse->status(), 'body' => $authResponse->body()]);

                    return new PaymentInitiationResult(
                        success: false,
                        transactionReference: $transaction->public_reference,
                        providerReference: null,
                        message: "Safaricom M-Pesa Auth Failed: {$errText}"
                    );
                }

                $token = $authResponse->json('access_token');
                $timestamp = date('YmdHis');
                $password = base64_encode($shortCode.$passKey.$timestamp);

                $stkHttp = Http::timeout(15);
                if ($env !== 'production') {
                    $stkHttp = $stkHttp->withoutVerifying();
                }

                $stkResponse = $stkHttp->withToken($token)
                    ->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", [
                        'BusinessShortCode' => $shortCode,
                        'Password' => $password,
                        'Timestamp' => $timestamp,
                        'TransactionType' => 'CustomerPayBillOnline',
                        'Amount' => (int) round($transaction->amount),
                        'PartyA' => $formattedPhone,
                        'PartyB' => $shortCode,
                        'PhoneNumber' => $formattedPhone,
                        'CallBackURL' => $config['callback_url'],
                        'AccountReference' => 'LindrCoins',
                        'TransactionDesc' => "Coin Package #{$transaction->package_id}",
                    ]);

                if ($stkResponse->successful() && ($stkResponse->json('ResponseCode') === '0' || $stkResponse->json('ResponseCode') === 0)) {
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

                $stkErrDesc = $stkResponse->json('ResponseDescription') ?? $stkResponse->json('errorMessage') ?? $stkResponse->body();
                Log::error('M-Pesa STK Push rejected by Safaricom', ['status' => $stkResponse->status(), 'body' => $stkResponse->body()]);

                return new PaymentInitiationResult(
                    success: false,
                    transactionReference: $transaction->public_reference,
                    providerReference: null,
                    message: "Safaricom M-Pesa STK Push Error: {$stkErrDesc}"
                );
            } catch (\Exception $e) {
                Log::error('M-Pesa STK Push exception: '.$e->getMessage());

                return new PaymentInitiationResult(
                    success: false,
                    transactionReference: $transaction->public_reference,
                    providerReference: null,
                    message: 'M-Pesa Connection Error: '.$e->getMessage()
                );
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
