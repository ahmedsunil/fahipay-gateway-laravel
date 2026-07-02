<?php

namespace Fahipay\Gateway\Contracts;

use Fahipay\Gateway\Enums\PaymentStatus;

interface PaymentHandlerInterface
{
    /**
     * Handle successful payment
     */
    public function handleSuccess(string $transactionId, ?string $approvalCode = null): void;

    /**
     * Handle failed payment
     */
    public function handleFailure(string $transactionId, ?string $reason = null): void;

    /**
     * Handle cancelled payment
     */
    public function handleCancellation(string $transactionId): void;

    /**
     * Handle pending payment
     */
    public function handlePending(string $transactionId): void;

    /**
     * Determine payment status from gateway response
     *
     * @param  array<string, mixed>  $response
     */
    public function determineStatus(array $response): PaymentStatus;

    /**
     * Process payment completion
     *
     * @param  array<string, mixed>  $data
     */
    public function process(string $transactionId, array $data): void;
}
