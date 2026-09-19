<?php

namespace App\Services;

use App\Enums\WithdrawalStatus;
use App\Models\PaymentProvider;
use App\Models\Withdrawal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MpesaService
{
    public function __construct(
        protected CreditLedgerService $creditLedgerService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Get combined M-Pesa configuration from config and database overrides.
     */
    public function getConfiguration(): array
    {
        $config = config('services.mpesa', []);
        $dbProvider = PaymentProvider::where('code', 'mpesa')->first();

        if ($dbProvider && ! empty($dbProvider->configuration)) {
            $config = array_merge($config, $dbProvider->configuration);
        }

        return [
            'environment' => $config['environment'] ?? 'sandbox',
            'consumer_key' => $config['consumer_key'] ?? null,
            'consumer_secret' => $config['consumer_secret'] ?? null,
            'shortcode' => $config['shortcode'] ?? '174379',
            'passkey' => $config['passkey'] ?? null,
            'b2c_shortcode' => $config['b2c_shortcode'] ?? ($config['shortcode'] ?? '600000'),
            'initiator_name' => $config['initiator_name'] ?? 'testapi',
            'security_credential' => $config['security_credential'] ?? null,
            'initiator_password' => $config['initiator_password'] ?? null,
            'b2c_result_url' => $config['b2c_result_url'] ?? (env('APP_URL').'/api/v1/webhooks/mpesa/b2c/result'),
            'b2c_timeout_url' => $config['b2c_timeout_url'] ?? (env('APP_URL').'/api/v1/webhooks/mpesa/b2c/timeout'),
            'callback_url' => $config['callback_url'] ?? (env('APP_URL').'/api/v1/webhooks/mpesa'),
        ];
    }

    /**
     * Format phone number to 254XXXXXXXXX standard.
     */
    public function formatPhoneNumber(string $phone): string
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

    /**
     * Obtain M-Pesa OAuth access token.
     */
    public function getAccessToken(): ?string
    {
        $config = $this->getConfiguration();
        $key = $config['consumer_key'];
        $secret = $config['consumer_secret'];

        if (empty($key) || empty($secret) || str_contains($key, 'mock')) {
            return 'mock_mpesa_access_token_'.now()->timestamp;
        }

        return Cache::remember('mpesa_access_token', 3500, function () use ($config, $key, $secret) {
            $env = $config['environment'] ?? 'sandbox';
            $baseUrl = $env === 'production'
                ? 'https://api.safaricom.co.ke'
                : 'https://sandbox.safaricom.co.ke';

            $response = Http::withBasicAuth($key, $secret)
                ->get("{$baseUrl}/oauth/v1/generate?grant_type=client_credentials");

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('M-Pesa OAuth token error', ['response' => $response->body()]);

            return null;
        });
    }

    /**
     * Dispatch B2C payout request to Safaricom Daraja API.
     */
    public function sendB2CPayout(Withdrawal $withdrawal): array
    {
        $config = $this->getConfiguration();
        $formattedPhone = $this->formatPhoneNumber($withdrawal->mpesa_number ?? '');
        $amount = (int) round($withdrawal->amount_kes ?? 0);

        $consumerKey = $config['consumer_key'];
        $consumerSecret = $config['consumer_secret'];

        // Sandbox / Mock simulation fallback
        if (empty($consumerKey) || empty($consumerSecret) || str_contains($consumerKey, 'mock') || str_contains($consumerKey, 'sandbox_key')) {
            $mockConversationId = 'AG_B2C_'.now()->format('YmdHis').'_'.rand(1000, 9999);
            $mockOriginatorId = (string) $withdrawal->id;

            return [
                'success' => true,
                'response_code' => '0',
                'response_description' => 'Accept the service request successfully.',
                'originator_conversation_id' => $mockOriginatorId,
                'conversation_id' => $mockConversationId,
                'is_simulated' => true,
            ];
        }

        $token = $this->getAccessToken();
        if (! $token) {
            return [
                'success' => false,
                'response_code' => 'AUTH_ERROR',
                'response_description' => 'Unable to generate M-Pesa OAuth token.',
                'is_simulated' => false,
            ];
        }

        try {
            $env = $config['environment'] ?? 'sandbox';
            $baseUrl = $env === 'production'
                ? 'https://api.safaricom.co.ke'
                : 'https://sandbox.safaricom.co.ke';

            $resultUrl = $config['b2c_result_url'] ?: route('webhooks.mpesa.b2c.result');
            $timeoutUrl = $config['b2c_timeout_url'] ?: route('webhooks.mpesa.b2c.timeout');

            $payload = [
                'InitiatorName' => $config['initiator_name'] ?? 'testapi',
                'SecurityCredential' => $config['security_credential'] ?? 'credential',
                'CommandID' => 'BusinessPayment',
                'Amount' => $amount,
                'PartyA' => $config['b2c_shortcode'] ?? '600000',
                'PartyB' => $formattedPhone,
                'Remarks' => 'Lindr Creator Payout',
                'QueueTimeOutURL' => $timeoutUrl,
                'ResultURL' => $resultUrl,
                'Occasion' => 'Withdrawal',
                'OriginatorConversationID' => (string) $withdrawal->id,
            ];

            $response = Http::withToken($token)
                ->post("{$baseUrl}/mpesa/b2c/v1/paymentrequest", $payload);

            if ($response->successful()) {
                $resData = $response->json();

                return [
                    'success' => ($resData['ResponseCode'] ?? '') === '0',
                    'response_code' => $resData['ResponseCode'] ?? 'UNKNOWN',
                    'response_description' => $resData['ResponseDescription'] ?? 'No description provided',
                    'originator_conversation_id' => $resData['OriginatorConversationID'] ?? (string) $withdrawal->id,
                    'conversation_id' => $resData['ConversationID'] ?? null,
                    'raw_response' => $resData,
                    'is_simulated' => false,
                ];
            }

            Log::error('M-Pesa B2C request failed', ['status' => $response->status(), 'body' => $response->body()]);

            return [
                'success' => false,
                'response_code' => (string) $response->status(),
                'response_description' => 'M-Pesa B2C endpoint error: '.$response->body(),
                'is_simulated' => false,
            ];
        } catch (\Exception $e) {
            Log::error('M-Pesa B2C Exception', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'response_code' => 'EXCEPTION',
                'response_description' => $e->getMessage(),
                'is_simulated' => false,
            ];
        }
    }

    /**
     * Process M-Pesa B2C Result Callback idempotently.
     */
    public function handleB2CResult(array $payload): array
    {
        $result = $payload['Result'] ?? $payload;
        $resultCode = (int) ($result['ResultCode'] ?? 1);
        $resultDesc = $result['ResultDesc'] ?? 'No result description';
        $originatorId = $result['OriginatorConversationID'] ?? null;
        $conversationId = $result['ConversationID'] ?? null;
        $transactionId = $result['TransactionID'] ?? null;

        // Parse MpesaReceiptNumber if in ResultParameters
        if (empty($transactionId) && isset($result['ResultParameters']['ResultParameter'])) {
            foreach ($result['ResultParameters']['ResultParameter'] as $param) {
                if (($param['Key'] ?? '') === 'TransactionReceipt') {
                    $transactionId = $param['Value'] ?? null;
                    break;
                }
            }
        }

        // Locate target withdrawal
        $withdrawal = null;
        if ($originatorId) {
            $withdrawal = Withdrawal::where('id', $originatorId)->first();
        }
        if (! $withdrawal && $conversationId) {
            $withdrawal = Withdrawal::where('provider_reference', $conversationId)->first();
        }

        if (! $withdrawal) {
            Log::warning('M-Pesa B2C callback: withdrawal not found', ['originator_id' => $originatorId, 'conversation_id' => $conversationId]);

            return ['status' => 'not_found', 'message' => 'Withdrawal record not found'];
        }

        // Idempotency check: if already in a final state, skip repeated financial execution
        if ($withdrawal->status->isFinal()) {
            return [
                'status' => 'already_processed',
                'withdrawal_id' => $withdrawal->id,
                'current_status' => $withdrawal->status->value,
            ];
        }

        /** @var WithdrawalService $withdrawalService */
        $withdrawalService = app(WithdrawalService::class);

        if ($resultCode === 0) {
            // Payout Succeeded on M-Pesa
            $withdrawalService->processWithdrawal(
                $withdrawal,
                WithdrawalStatus::Paid,
                null,
                'M-Pesa B2C payout completed via callback.',
                $transactionId ?? $conversationId
            );

            return [
                'status' => 'success',
                'withdrawal_id' => $withdrawal->id,
                'mpesa_receipt' => $transactionId,
            ];
        }

        // Payout Failed on M-Pesa -> Revert credits atomically exactly once
        $withdrawalService->processWithdrawal(
            $withdrawal,
            WithdrawalStatus::Failed,
            null,
            'M-Pesa callback rejected payout: '.$resultDesc,
            $conversationId,
            $resultDesc
        );

        return [
            'status' => 'failed_reverted',
            'withdrawal_id' => $withdrawal->id,
            'reason' => $resultDesc,
        ];
    }

    /**
     * Handle M-Pesa B2C Queue Timeout Callback without double-refunding or double-payout.
     */
    public function handleB2CTimeout(array $payload): array
    {
        $result = $payload['Result'] ?? $payload;
        $originatorId = $result['OriginatorConversationID'] ?? null;
        $conversationId = $result['ConversationID'] ?? null;
        $resultDesc = $result['ResultDesc'] ?? 'M-Pesa request timed out';

        $withdrawal = null;
        if ($originatorId) {
            $withdrawal = Withdrawal::where('id', $originatorId)->first();
        }
        if (! $withdrawal && $conversationId) {
            $withdrawal = Withdrawal::where('provider_reference', $conversationId)->first();
        }

        if ($withdrawal && ! $withdrawal->status->isFinal()) {
            $withdrawal->update([
                'admin_notes' => trim(($withdrawal->admin_notes ?? '')."\nTimeout logged: ".$resultDesc),
            ]);
        }

        return [
            'status' => 'timeout_logged',
            'withdrawal_id' => $withdrawal?->id,
        ];
    }
}
