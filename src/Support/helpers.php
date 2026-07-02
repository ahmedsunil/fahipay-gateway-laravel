<?php

use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;

if (!function_exists('fahipay_amount')) {
    function fahipay_amount(float $amount, string $currency = 'MVR'): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }
}

if (!function_exists('fahipay_status')) {
    function fahipay_status(string|PaymentStatus $status): string
    {
        if ($status instanceof PaymentStatus) {
            return $status->label();
        }
        
        return PaymentStatus::fromString($status)->label();
    }
}

if (!function_exists('fahipay_redirect')) {
    function fahipay_redirect(string $transactionId, float $amount, ?string $description = null)
    {
        $payment = FahipayGateway::createPayment($transactionId, $amount, $description);
        
        if ($payment->paymentUrl) {
            return redirect($payment->paymentUrl);
        }
        
        return back()->with('error', $payment->rawResponse['message'] ?? 'Payment failed');
    }
}

if (!function_exists('fahipay_config')) {
    function fahipay_config(?string $key = null, $default = null)
    {
        $config = config('fahipay');
        
        if ($key === null) {
            return $config;
        }
        
        return $config[$key] ?? $default;
    }
}

if (!function_exists('fahipay_is_test_mode')) {
    function fahipay_is_test_mode(): bool
    {
        return config('fahipay.test_mode', false);
    }
}

if (!function_exists('fahipay_is_configured')) {
    function fahipay_is_configured(): bool
    {
        return !empty(config('fahipay.shop_id')) && !empty(config('fahipay.secret_key'));
    }
}

if (!function_exists('fahipay_payment_url')) {
    function fahipay_payment_url(string $transactionId, float $amount): string
    {
        return FahipayGateway::getPaymentUrl($transactionId, $amount);
    }
}

if (!function_exists('fahipay_verify')) {
    function fahipay_verify(string $transactionId): ?FahipayPayment
    {
        return FahipayPayment::where('transaction_id', $transactionId)->first();
    }
}

if (!function_exists('fahipay_generate_signature')) {
    function fahipay_generate_signature(string $transactionId, float|int $amount): string
    {
        $amountInCents = (int) round($amount * 100);
        return FahipayGateway::generateSignature($transactionId, $amountInCents);
    }
}
