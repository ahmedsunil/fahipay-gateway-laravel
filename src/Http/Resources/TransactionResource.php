<?php

namespace Fahipay\Gateway\Http\Resources;

use Fahipay\Gateway\Data\TransactionData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TransactionData
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'amount' => $this->amount,
            'amount_formatted' => $this->getAmountFormatted(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method,
            'approval_code' => $this->approvalCode,
            'error_message' => $this->errorMessage,
            'time' => $this->time?->toIso8601String(),
            'created_at' => $this->time?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
        ];
    }
}
