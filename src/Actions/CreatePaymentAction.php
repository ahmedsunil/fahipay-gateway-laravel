<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Support\Str;

class CreatePaymentAction
{
    public function execute(array $data): PaymentData
    {
        $transactionId = $data['transaction_id'] ?? $this->generateTransactionId();
        $amount = (float) $data['amount'];
        $description = $data['description'] ?? null;
        $metadata = $data['metadata'] ?? [];
        $callbackUrl = $data['callback_url'] ?? null;

        if ($callbackUrl !== null) {
            $this->assertAllowedCallbackUrl($callbackUrl);
            $metadata['callback_url'] = $callbackUrl;
        }

        // FahipayGateway is a singleton. Mutating its return URL globally would
        // leak one request's callback into another in long-lived workers
        // (e.g. Octane/queues), so we restore the previous value afterwards.
        $gateway = FahipayGateway::getFacadeRoot();
        $previousReturnUrl = $gateway->getReturnUrl();

        try {
            if ($callbackUrl !== null) {
                $gateway->setReturnUrl($callbackUrl);
            }

            return $gateway->createPayment(
                $transactionId,
                $amount,
                $description,
                $metadata
            );
        } finally {
            if ($callbackUrl !== null) {
                $gateway->setReturnUrl($previousReturnUrl);
            }
        }
    }

    /**
     * Reject callback URLs that point at hosts outside the configured allow-list.
     * Prevents open-redirect / SSRF via an attacker-supplied callback_url.
     */
    protected function assertAllowedCallbackUrl(string $url): void
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new FahipayException('Invalid callback URL');
        }

        $allowedHosts = config('fahipay.allowed_redirect_hosts', []);

        if (empty($allowedHosts)) {
            if (config('fahipay.allow_unrestricted_callback_urls', false)) {
                return;
            }

            throw new FahipayException('Callback URL host is not allowed');
        }

        $host = parse_url($url, PHP_URL_HOST);

        if (!in_array($host, $allowedHosts, true)) {
            throw new FahipayException('Callback URL host is not allowed');
        }
    }

    protected function generateTransactionId(): string
    {
        $prefix = config('fahipay.payment.prefix', 'PAY');
        $length = config('fahipay.payment.unique_id_length', 12);

        return $prefix . '-' . Str::random($length);
    }
}
