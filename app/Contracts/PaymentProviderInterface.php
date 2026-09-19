<?php

namespace App\Contracts;

use App\DTOs\PaymentInitiationResult;
use App\DTOs\PaymentVerificationResult;
use App\DTOs\WebhookParseResult;
use App\Models\PaymentTransaction;

interface PaymentProviderInterface
{
    /**
     * Unique code of the provider (e.g., 'korapay', 'flutterwave', 'mpesa', 'google_pay').
     */
    public function getCode(): string;

    /**
     * Display name of the provider.
     */
    public function getName(): string;

    /**
     * Initiate payment transaction.
     */
    public function initiatePayment(PaymentTransaction $transaction, array $params = []): PaymentInitiationResult;

    /**
     * Verify payment status directly with provider API.
     */
    public function verifyPayment(PaymentTransaction $transaction): PaymentVerificationResult;

    /**
     * Parse and validate incoming webhook payload.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function parseWebhook(array $payload, array $headers = []): WebhookParseResult;

    /**
     * Perform quick connectivity / credential health check.
     */
    public function checkHealth(): bool;
}
