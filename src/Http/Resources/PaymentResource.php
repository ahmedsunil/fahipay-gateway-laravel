<?php

namespace Fahipay\Gateway\Http\Resources;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FahipayPayment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transaction_id' => $this->transaction_id,
            'merchant_id' => $this->merchant_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency ?? 'MVR',
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'method' => $this->method,
            'approval_code' => $this->approval_code,
            'description' => $this->description,
            'metadata' => $this->metadata,
            'error_message' => $this->error_message,
            'initiated_at' => $this->initiated_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }

    public function toJson($options = 0): string
    {
        return parent::toJson($options);
    }
}
