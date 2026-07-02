<?php

namespace Fahipay\Gateway\Data;

use Carbon\Carbon;
use Fahipay\Gateway\Enums\PaymentStatus;
use Spatie\LaravelData\Data;

class TransactionData extends Data
{
    /**
     * @param  array<string, mixed>  $rawResponse
     */
    public function __construct(
        public string $transactionId,
        public float $amount,
        public PaymentStatus $status,
        public ?string $method = null,
        public ?string $approvalCode = null,
        public ?Carbon $time = null,
        public ?string $errorMessage = null,
        public array $rawResponse = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function isPending(): bool
    {
        return $this->status->isPending();
    }

    public function isFailed(): bool
    {
        return $this->status->isFailed();
    }

    public function getAmountFormatted(): string
    {
        return number_format($this->amount, 2);
    }

    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'amount_formatted' => $this->getAmountFormatted(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method,
            'approval_code' => $this->approvalCode,
            'time' => $this->time?->toIso8601String(),
            'error_message' => $this->errorMessage,
        ];
    }
}
