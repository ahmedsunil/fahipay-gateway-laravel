<?php

use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\Request;

beforeEach(function () {
    config(['fahipay.shop_id' => 'test_shop']);
    config(['fahipay.secret_key' => 'test_secret']);
    config(['fahipay.test_mode' => true]);
});

function callbackRequest(FahipayGateway $gateway, array $overrides = []): Request
{
    $params = array_merge([
        'Success' => 'true',
        'ShoppingCartID' => 'TEST-001',
        'ApprovalCode' => 'APPROVAL123',
    ], $overrides);

    if (($params['Success'] === 'true' || $params['Success'] === '1') && ! array_key_exists('Signature', $overrides)) {
        $params['Signature'] = $gateway->generateCallbackSignature(
            $params['Success'],
            $params['ShoppingCartID'],
            $params['ApprovalCode'] ?? null
        );
    }

    return Request::create('/callback', 'GET', $params);
}

test('can validate callback with valid signature', function () {
    $gateway = app(FahipayGateway::class);

    expect($gateway->validateCallback(callbackRequest($gateway)))->toBeTrue();
});

test('rejects callback with invalid signature', function () {
    $gateway = app(FahipayGateway::class);

    $request = callbackRequest($gateway, ['Signature' => 'invalid_signature']);

    expect($gateway->validateCallback($request))->toBeFalse();
});

test('accepts documented success callback without timestamp', function () {
    $gateway = app(FahipayGateway::class);

    expect($gateway->validateCallback(callbackRequest($gateway)))->toBeTrue();
});

test('accepts documented failed callback without signature', function () {
    $gateway = app(FahipayGateway::class);

    $request = Request::create('/callback', 'GET', [
        'Success' => 'false',
        'ShoppingCartID' => 'FAILED-001',
        'ErrorMessage' => 'Insufficient funds',
    ]);

    expect($gateway->validateDisplayCallback($request))->toBeTrue();
    expect($gateway->validateCallback($request))->toBeFalse();
});

test('can handle successful callback', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('CALLBACK-001', 'test_shop', 100.00);

    $request = callbackRequest($gateway, ['ShoppingCartID' => 'CALLBACK-001']);

    $transaction = $gateway->handleCallback($request);

    expect($transaction->transactionId)->toBe('CALLBACK-001');
    expect($transaction->status)->toBe(PaymentStatus::COMPLETED);
    expect($transaction->approvalCode)->toBe('APPROVAL123');
    expect(FahipayPayment::where('transaction_id', 'CALLBACK-001')->first()->status)
        ->toBe(PaymentStatus::COMPLETED);
});

test('default success route updates package payment record', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('ROUTE-CALLBACK-001', 'test_shop', 125.00);

    $request = callbackRequest($gateway, ['ShoppingCartID' => 'ROUTE-CALLBACK-001']);

    $response = $this->get('/fahipay/callback/success?'.http_build_query($request->query->all()));

    $response->assertOk();
    expect(FahipayPayment::where('transaction_id', 'ROUTE-CALLBACK-001')->first()->status)
        ->toBe(PaymentStatus::COMPLETED);
});

test('signed failed callback can update package payment record', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('CALLBACK-FAILED-SIGNED', 'test_shop', 50.00);

    $signature = $gateway->generateCallbackSignature('false', 'CALLBACK-FAILED-SIGNED', null);
    $request = Request::create('/callback', 'GET', [
        'Success' => 'false',
        'ShoppingCartID' => 'CALLBACK-FAILED-SIGNED',
        'ErrorMessage' => 'Declined',
        'Signature' => $signature,
    ]);

    $transaction = $gateway->handleCallback($request);

    $payment = FahipayPayment::where('transaction_id', 'CALLBACK-FAILED-SIGNED')->first();

    expect($transaction->status)->toBe(PaymentStatus::FAILED)
        ->and($payment->status)->toBe(PaymentStatus::FAILED)
        ->and($payment->error_message)->toBe('Declined');
});

test('signed error route updates package payment record as failed', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('ROUTE-ERROR-001', 'test_shop', 75.00);

    $query = [
        'Success' => 'false',
        'ShoppingCartID' => 'ROUTE-ERROR-001',
        'ErrorMessage' => 'Insufficient funds',
        'Signature' => $gateway->generateCallbackSignature('false', 'ROUTE-ERROR-001', null),
    ];

    $response = $this->get('/fahipay/callback/error?'.http_build_query($query));

    $response->assertOk();
    $payment = FahipayPayment::where('transaction_id', 'ROUTE-ERROR-001')->first();

    expect($payment->status)->toBe(PaymentStatus::FAILED)
        ->and($payment->error_message)->toBe('Insufficient funds');
});

test('signed cancel route updates package payment record as cancelled', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('ROUTE-CANCEL-001', 'test_shop', 75.00);

    $query = [
        'Success' => 'false',
        'ShoppingCartID' => 'ROUTE-CANCEL-001',
        'Signature' => $gateway->generateCallbackSignature('false', 'ROUTE-CANCEL-001', null),
    ];

    $response = $this->get('/fahipay/callback/cancel?'.http_build_query($query));

    $response->assertOk();
    expect(FahipayPayment::where('transaction_id', 'ROUTE-CANCEL-001')->first()->status)
        ->toBe(PaymentStatus::CANCELLED);
});

test('unsigned failed callback is display-only and not state-changing', function () {
    $gateway = app(FahipayGateway::class);

    FahipayPayment::createPayment('CALLBACK-002', 'test_shop', 50.00);

    $request = callbackRequest($gateway, [
        'Success' => 'false',
        'ShoppingCartID' => 'CALLBACK-002',
        'ApprovalCode' => null,
    ]);

    expect($gateway->validateDisplayCallback($request))->toBeTrue();
    expect($gateway->validateCallback($request))->toBeFalse();
    expect($gateway->validateStateChangingCallback($request))->toBeFalse();
    expect(fn () => $gateway->handleCallback($request))
        ->toThrow(FahipayException::class);
});

test('unsigned error route renders without changing package payment record', function () {
    FahipayPayment::createPayment('ROUTE-ERROR-UNSIGNED', 'test_shop', 75.00);

    $response = $this->get('/fahipay/callback/error?'.http_build_query([
        'Success' => 'false',
        'ShoppingCartID' => 'ROUTE-ERROR-UNSIGNED',
        'ErrorMessage' => 'Insufficient funds',
    ]));

    $response->assertOk();
    expect(FahipayPayment::where('transaction_id', 'ROUTE-ERROR-UNSIGNED')->first()->status)
        ->toBe(PaymentStatus::PENDING);
});

test('unsigned cancel route renders without changing package payment record', function () {
    FahipayPayment::createPayment('ROUTE-CANCEL-UNSIGNED', 'test_shop', 75.00);

    $response = $this->get('/fahipay/callback/cancel?'.http_build_query([
        'Success' => 'false',
        'ShoppingCartID' => 'ROUTE-CANCEL-UNSIGNED',
    ]));

    $response->assertOk();
    expect(FahipayPayment::where('transaction_id', 'ROUTE-CANCEL-UNSIGNED')->first()->status)
        ->toBe(PaymentStatus::PENDING);
});

test('rejects callbacks when gateway credentials are missing', function () {
    config(['fahipay.shop_id' => '']);
    config(['fahipay.secret_key' => '']);

    $gateway = new FahipayGateway;
    $request = Request::create('/callback', 'GET', [
        'Success' => 'false',
        'ShoppingCartID' => 'FAILED-UNCONFIGURED',
    ]);

    expect($gateway->validateDisplayCallback($request))->toBeFalse();
    expect($gateway->validateCallback($request))->toBeFalse();
});

test('rejects callback with tampered transaction id', function () {
    $gateway = app(FahipayGateway::class);

    // Signature is generated for ORIGINAL-001 but request claims TAMPERED-001.
    $signature = $gateway->generateCallbackSignature('true', 'ORIGINAL-001', 'APPROVAL123');

    $request = Request::create('/callback', 'GET', [
        'Success' => 'true',
        'ShoppingCartID' => 'TAMPERED-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);

    expect($gateway->validateCallback($request))->toBeFalse();
});

test('rejects callback with tampered success flag', function () {
    $gateway = app(FahipayGateway::class);

    // Signed as success=true, replayed as success=false.
    $signature = $gateway->generateCallbackSignature('true', 'TEST-001', 'APPROVAL123');

    $request = Request::create('/callback', 'GET', [
        'Success' => 'false',
        'ShoppingCartID' => 'TEST-001',
        'ApprovalCode' => 'APPROVAL123',
        'Signature' => $signature,
    ]);

    expect($gateway->validateCallback($request))->toBeFalse();
});

test('rejects successful callback without approval code', function () {
    $gateway = app(FahipayGateway::class);

    $request = callbackRequest($gateway, [
        'ShoppingCartID' => 'TEST-002',
        'ApprovalCode' => null,
        'Signature' => '',
    ]);

    expect($gateway->validateCallback($request))->toBeFalse();
});

test('webhook endpoint exists and responds', function () {
    $response = $this->postJson('/api/fahipay/webhook', [
        'Success' => 'false',
        'ShoppingCartID' => 'WEBHOOK-001',
        'Message' => 'Test',
    ]);

    // API webhook no longer uses the unrelated raw-body signature middleware;
    // unsigned failure callbacks are rejected for state-changing processing.
    $response->assertStatus(400);
});

test('api payment endpoints are reachable when enabled', function () {
    $response = $this->getJson('/api/fahipay/payments');
    $response->assertStatus(200);

    $response = $this->postJson('/api/fahipay/payments', [
        'amount' => 100,
    ]);
    $response->assertStatus(422);
});
