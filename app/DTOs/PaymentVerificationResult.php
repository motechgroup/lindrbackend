<?php

namespace App\DTOs;

class PaymentVerificationResult
{
    public function __construct(
        public bool $success,
        public string $status,
        public ?string $providerReference = null,
        public ?string $receiptNumber = null,
        public string $message = '',
        public array $rawResponse = []
    ) {}
}
