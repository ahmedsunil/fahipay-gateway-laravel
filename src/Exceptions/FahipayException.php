<?php

namespace Fahipay\Gateway\Exceptions;

use Exception;

class FahipayException extends Exception
{
    public static function notConfigured(): self
    {
        return new self('FahiPay is not configured. Please set merchant_id and secret_key.');
    }

    public static function invalidSignature(): self
    {
        return new self('Invalid signature');
    }

    public static function paymentFailed(?string $message = null): self
    {
        return new self($message ?? 'Payment failed');
    }

    public static function apiError(string $message, int $code = 0): self
    {
        return new self($message, $code);
    }
}
