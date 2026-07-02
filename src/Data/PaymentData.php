<?php

namespace Fahipay\Gateway\Data;

use Fahipay\Gateway\Enums\PaymentStatus;
use Spatie\LaravelData\Data;

class PaymentData extends Data
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $transactionId,
        public float $amount,
        public PaymentStatus $status,
        public ?string $paymentUrl = null,
        public ?string $description = null,
        public ?array $metadata = null,
        public array $rawResponse = [],
    ) {}

    public function getPaymentUrl(): ?string
    {
        return $this->paymentUrl;
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'status' => $this->status->value,
            'payment_url' => $this->paymentUrl,
            'description' => $this->description,
            'metadata' => $this->metadata,
        ];
    }
}
