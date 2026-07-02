<?php

namespace Fahipay\Gateway\Contracts;

use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Data\TransactionData;
use Illuminate\Http\Request;

interface GatewayInterface
{
    /**
     * Create a new payment transaction
     */
    public function createPayment(string $transactionId, float $amount, ?string $description = null, ?array $metadata = []): PaymentData;

    /**
     * Query transaction status
     */
    public function getTransaction(string $transactionId): ?TransactionData;

    /**
     * Verify callback signature
     */
    public function verifySignature(string $success, string $transactionId, ?string $approvalCode, int|string $signatureOrTimestamp, ?string $signature = null): bool;

    /**
     * Validate a signed callback from FahiPay
     */
    public function validateCallback(Request $request): bool;

    /**
     * Validate browser-return callbacks that are used only for display
     */
    public function validateDisplayCallback(Request $request): bool;

    /**
     * Validate callback that can change local merchant state
     */
    public function validateStateChangingCallback(Request $request): bool;

    /**
     * Handle callback from payment gateway
     */
    public function handleCallback(Request $request): TransactionData;

    /**
     * Generate payment URL
     */
    public function getPaymentUrl(string $transactionId, float $amount): string;

    /**
     * Check if gateway is properly configured
     */
    public function isConfigured(): bool;

    /**
     * Get merchant ID
     */
    public function getMerchantId(): ?string;

    /**
     * Check if in test mode
     */
    public function isTestMode(): bool;
}
