<?php

use Fahipay\Gateway\Contracts\GatewayInterface;
use Fahipay\Gateway\FahipayGateway as Gateway;

beforeEach(function () {
    config(['fahipay.shop_id' => 'test_shop']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
});

test('gateway can be instantiated', function () {
    $gateway = app(Gateway::class);

    expect($gateway)->toBeInstanceOf(Gateway::class);
});

test('gateway interface resolves to compatible implementation', function () {
    $gateway = app(GatewayInterface::class);

    expect($gateway)->toBeInstanceOf(GatewayInterface::class)
        ->and($gateway)->toBeInstanceOf(Gateway::class);
});

test('gateway checks if configured', function () {
    $gateway = app(Gateway::class);

    expect($gateway->isConfigured())->toBeTrue();
});

test('gateway gets shop id', function () {
    $gateway = app(Gateway::class);

    expect($gateway->getShopId())->toBe('test_shop');
});

test('gateway can set custom shop id', function () {
    $gateway = app(Gateway::class);
    $gateway->setShopId('custom_shop');

    expect($gateway->getShopId())->toBe('custom_shop');
});

test('gateway can toggle test mode', function () {
    config(['fahipay.web_url' => 'https://fahipay.mv']);
    config(['fahipay.test_web_url' => 'https://test.fahipay.mv']);

    $gateway = new Gateway;

    expect($gateway->isTestMode())->toBeTrue();
    expect($gateway->getWebUrl())->toBe('https://test.fahipay.mv');

    $gateway->setTestMode(false);

    expect($gateway->isTestMode())->toBeFalse();
    expect($gateway->getWebUrl())->toBe('https://fahipay.mv');
});

test('gateway generates valid signature', function () {
    $gateway = app(Gateway::class);

    $signature = $gateway->generateSignature('TEST-001', 10000, 1700000000);

    expect($signature)->toBeString()->not->toBeEmpty();
});

test('gateway verifies callback signature correctly', function () {
    $gateway = app(Gateway::class);

    $timestamp = time();
    $signature = $gateway->generateCallbackSignature('true', 'TEST-001', 'APPROVAL123', $timestamp);

    $isValid = $gateway->verifySignature('true', 'TEST-001', 'APPROVAL123', $timestamp, $signature);

    expect($isValid)->toBeTrue();
});

test('gateway detects invalid signature', function () {
    $gateway = app(Gateway::class);

    $isValid = $gateway->verifySignature('true', 'TEST-001', 'APPROVAL123', time(), 'invalid_signature');

    expect($isValid)->toBeFalse();
});

test('gateway can set return url', function () {
    $gateway = app(Gateway::class);
    $gateway->setReturnUrl('https://example.com/return');

    expect($gateway->getReturnUrl())->toBe('https://example.com/return');
});
