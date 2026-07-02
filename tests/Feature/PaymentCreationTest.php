<?php

use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCompletedEvent;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\FahipayGateway as Gateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    config(['fahipay.shop_id' => 'test_shop']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
    config(['fahipay.return_url' => 'http://localhost/return']);
    config(['fahipay.cancel_url' => 'http://localhost/cancel']);
    config(['fahipay.error_url' => 'http://localhost/error']);
});

test('can create payment via facade', function () {
    $payment = FahipayGateway::createPayment('TEST-001', 100.00, 'Test payment');

    expect($payment->transactionId)->toBe('TEST-001');
    expect($payment->amount)->toBe(100.00);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
});

test('can generate valid signature', function () {
    $signature = FahipayGateway::generateSignature('TEST-001', 10000, 1700000000);

    expect($signature)->toBeString()->not->toBeEmpty();
});

test('can verify valid callback signature', function () {
    $gateway = app(Gateway::class);
    $timestamp = time();
    $signature = $gateway->generateCallbackSignature('true', 'TEST-001', 'APPROVAL123', $timestamp);

    $isValid = $gateway->verifySignature('true', 'TEST-001', 'APPROVAL123', $timestamp, $signature);

    expect($isValid)->toBeTrue();
});

test('detects invalid signature', function () {
    $isValid = FahipayGateway::verifySignature('true', 'TEST-001', 'APPROVAL123', time(), 'invalid_signature');

    expect($isValid)->toBeFalse();
});

test('checks if gateway is configured', function () {
    expect(FahipayGateway::isConfigured())->toBeTrue();
});

test('checks test mode status', function () {
    expect(FahipayGateway::isTestMode())->toBeTrue();

    FahipayGateway::setTestMode(false);

    expect(FahipayGateway::isTestMode())->toBeFalse();
});

test('can set custom return url', function () {
    FahipayGateway::setReturnUrl('https://custom.com/return');

    expect(FahipayGateway::getReturnUrl())->toBe('https://custom.com/return');
});

test('can set custom shop credentials', function () {
    FahipayGateway::setShopId('custom_shop')->setSecretKey('custom_secret');

    expect(FahipayGateway::getShopId())->toBe('custom_shop');
});

test('fahipay payment model can be created', function () {
    $payment = FahipayPayment::createPayment(
        'TEST-001',
        'test_shop',
        100.00,
        'Test payment'
    );

    expect($payment->transaction_id)->toBe('TEST-001');
    expect((float) $payment->amount)->toBe(100.00);
    expect($payment->status)->toBe(PaymentStatus::PENDING);
});

test('fahipay payment model can mark as completed', function () {
    Event::fake([PaymentCompletedEvent::class]);

    $payment = FahipayPayment::createPayment('TEST-002', 'test_shop', 50.00);

    $payment->markAsCompleted('APPROVAL123');

    expect($payment->fresh()->status)->toBe(PaymentStatus::COMPLETED);
    expect($payment->fresh()->approval_code)->toBe('APPROVAL123');

    Event::assertDispatched(PaymentCompletedEvent::class);
});

test('fahipay payment model can mark as failed', function () {
    $payment = FahipayPayment::createPayment('TEST-003', 'test_shop', 75.00);

    $payment->markAsFailed('Card declined');

    expect($payment->fresh()->status)->toBe(PaymentStatus::FAILED);
    expect($payment->fresh()->error_message)->toBe('Card declined');
});

test('payment status enum works correctly', function () {
    expect(PaymentStatus::PENDING->label())->toBe('Pending');
    expect(PaymentStatus::COMPLETED->isSuccessful())->toBeTrue();
    expect(PaymentStatus::FAILED->isFailed())->toBeTrue();
    expect(PaymentStatus::PENDING->isPending())->toBeTrue();
});

test('can get payment url', function () {
    $gateway = new class extends Gateway
    {
        protected function requestPaymentRedirect(string $webUrl, array $params, string $cookiePath): ?string
        {
            return 'https://test.fahipay.mv/payment/session-123';
        }
    };

    $url = $gateway->getPaymentUrl('TEST-001', 100.00);

    expect($url)->toBeString()
        ->toBe('https://test.fahipay.mv/payment/session-123');
});

test('payment url fails when fahipay does not return a valid redirect', function () {
    $gateway = new class extends Gateway
    {
        protected function requestPaymentRedirect(string $webUrl, array $params, string $cookiePath): ?string
        {
            return null;
        }
    };

    expect(fn () => $gateway->getPaymentUrl('TEST-001', 100.00))
        ->toThrow(FahipayException::class, 'Unable to create FahiPay payment redirect');
});
