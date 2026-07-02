<?php

namespace Fahipay\Gateway\Models;

use Fahipay\Gateway\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transaction_id
 * @property string $merchant_id
 * @property string $amount
 * @property string|null $currency
 * @property PaymentStatus $status
 * @property string|null $method
 * @property string|null $approval_code
 * @property string|null $description
 * @property array<string, mixed>|null $metadata
 * @property string|null $error_message
 * @property Carbon|null $initiated_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FahipayPayment extends Model
{
    protected $table = 'fahipay_payments';

    protected $fillable = [
        'transaction_id',
        'merchant_id',
        'amount',
        'currency',
        'status',
        'method',
        'approval_code',
        'description',
        'metadata',
        'error_message',
        'initiated_at',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'initiated_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => PaymentStatus::class,
    ];

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function createPayment(
        string $transactionId,
        string $merchantId,
        float $amount,
        ?string $description = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'transaction_id' => $transactionId,
            'merchant_id' => $merchantId,
            'amount' => $amount,
            'description' => $description,
            'metadata' => $metadata,
            'status' => PaymentStatus::PENDING,
            'initiated_at' => now(),
        ]);
    }

    public function markAsCompleted(?string $approvalCode = null): void
    {
        $this->transitionTo(PaymentStatus::COMPLETED, [
            'approval_code' => $approvalCode,
        ]);
    }

    public function markAsFailed(?string $errorMessage = null): void
    {
        $this->transitionTo(PaymentStatus::FAILED, [
            'error_message' => $errorMessage,
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->transitionTo(PaymentStatus::CANCELLED);
    }

    /**
     * Transition the payment to a new status atomically.
     *
     * COMPLETED is terminal: a replayed or late failure/cancel callback must
     * not undo a confirmed payment. The row is re-read under a pessimistic
     * lock so concurrent webhook + expiry-job updates cannot interleave.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function transitionTo(PaymentStatus $status, array $attributes = []): void
    {
        if ($this->isCompleted()) {
            return;
        }

        $this->getConnection()->transaction(function () use ($status, $attributes) {
            $current = static::query()
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if ($current === null || $current->isCompleted()) {
                return;
            }

            $current->update($attributes + [
                'status' => $status,
                'completed_at' => now(),
            ]);

            $this->setRawAttributes($current->getAttributes(), true);
        });
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PENDING);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::COMPLETED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::FAILED);
    }

    public function isCompleted(): bool
    {
        return $this->status === PaymentStatus::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::PENDING;
    }

    public function isFailed(): bool
    {
        return in_array($this->status, [PaymentStatus::FAILED, PaymentStatus::CANCELLED]);
    }
}
