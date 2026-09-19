<?php

namespace App\DTOs;

class WebhookParseResult
{
    public function __construct(
        public bool $isValid,
        public ?string $event = null,
        public ?string $transactionReference = null,
        public ?string $providerReference = null,
        public ?string $status = null,
        public ?string $receiptNumber = null,
        public array $payload = []
    ) {}
}
