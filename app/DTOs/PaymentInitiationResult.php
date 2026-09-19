<?php

namespace App\DTOs;

class PaymentInitiationResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionReference = null,
        public ?string $providerReference = null,
        public ?string $checkoutUrl = null,
        public ?array $actionData = null,
        public string $message = '',
        public array $rawResponse = []
    ) {}
}
