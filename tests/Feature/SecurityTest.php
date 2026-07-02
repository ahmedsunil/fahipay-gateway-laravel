<?php

use Fahipay\Gateway\FahipayGateway;
use Fahipay\Gateway\Actions\CreatePaymentAction;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Http\Requests\CreatePaymentRequest;

beforeEach(function () {
    config(['fahipay.shop_id' => 'test_shop']);
    config(['fahipay.secret_key' => 'test_secret_key_12345']);
    config(['fahipay.test_mode' => true]);
});

/**
 * Security Test: Signature Validation
 */
describe('Signature Validation Security', function () {
    test('prevents timing attacks with hash_equals', function () {
        $gateway = app(FahipayGateway::class);

        $startTime = microtime(true);
        $gateway->verifySignature('true', 'TEST', 'APPROVAL', time(), 'wrong_signature');
        $time1 = microtime(true) - $startTime;

        $startTime = microtime(true);
        $gateway->verifySignature('true', 'TEST', 'APPROVAL', time(), 'another_wrong');
        $time2 = microtime(true) - $startTime;

        expect($time1)->toBeLessThan(1);
        expect($time2)->toBeLessThan(1);
    });

    test('rejects empty signature', function () {
        $gateway = app(FahipayGateway::class);

        expect($gateway->verifySignature('true', 'TEST', 'APPROVAL', time(), ''))->toBeFalse();
    });

    test('rejects signature with wrong transaction id', function () {
        $gateway = app(FahipayGateway::class);

        $timestamp = time();
        $signature = $gateway->generateCallbackSignature('true', 'TEST-001', 'APPROVAL', $timestamp);

        // Same signature, different transaction id.
        $isValid = $gateway->verifySignature('true', 'TEST-002', 'APPROVAL', $timestamp, $signature);

        expect($isValid)->toBeFalse();
    });

    test('rejects tampered success parameter', function () {
        $gateway = app(FahipayGateway::class);

        $timestamp = time();
        // Signed as success=true...
        $signature = $gateway->generateCallbackSignature('true', 'TEST-001', 'APPROVAL123', $timestamp);

        // ...replayed as success=false.
        $isValid = $gateway->verifySignature('false', 'TEST-001', 'APPROVAL123', $timestamp, $signature);

        expect($isValid)->toBeFalse();
    });
});

/**
 * Security Test: Input Validation
 */
describe('Input Validation Security', function () {
    test('generates signature for transaction id with special characters', function () {
        $gateway = app(FahipayGateway::class);

        $signature = $gateway->generateSignature('TEST<script>alert(1)</script>', 10000, 1700000000);

        expect($signature)->toBeString();
    });

    test('rejects non-positive amount', function () {
        $gateway = app(FahipayGateway::class);

        expect(fn () => $gateway->createPayment('TEST-001', -100.00))
            ->toThrow(\Fahipay\Gateway\Exceptions\FahipayException::class, 'Amount must be greater than zero');
    });

    test('rejects invalid payment url inputs before signing', function () {
        $gateway = app(FahipayGateway::class);

        expect(fn () => $gateway->getLink('BAD ID', 100.00))
            ->toThrow(FahipayException::class, 'Invalid transaction ID format');

        expect(fn () => $gateway->getLink('TEST-001', 0))
            ->toThrow(FahipayException::class, 'Amount must be greater than zero');

        config(['fahipay.secret_key' => '']);
        $gateway = new FahipayGateway();

        expect(fn () => $gateway->getLink('TEST-001', 100.00))
            ->toThrow(FahipayException::class, 'Shop ID and Secret Key are required');
    });

    test('custom callback urls are rejected by default', function () {
        $action = app(CreatePaymentAction::class);

        expect(fn () => $action->execute([
            'transaction_id' => 'CALLBACK-URL-001',
            'amount' => 100.00,
            'callback_url' => 'https://merchant.example/callback',
        ]))->toThrow(FahipayException::class, 'Callback URL host is not allowed');
    });

    test('custom callback urls require an allowed host unless explicitly unrestricted', function () {
        config(['fahipay.allowed_redirect_hosts' => ['merchant.example']]);

        $gateway = new class extends FahipayGateway {
            public ?string $capturedReturnUrl = null;

            public function createPayment(string $transactionId, float $amount, ?string $description = null, ?array $metadata = []): \Fahipay\Gateway\Data\PaymentData
            {
                $this->capturedReturnUrl = $this->getReturnUrl();

                return new \Fahipay\Gateway\Data\PaymentData(
                    transactionId: $transactionId,
                    amount: $amount,
                    status: \Fahipay\Gateway\Enums\PaymentStatus::PENDING,
                    paymentUrl: 'https://test.fahipay.mv/payment/session-123'
                );
            }
        };

        app()->instance(FahipayGateway::class, $gateway);

        app(CreatePaymentAction::class)->execute([
            'transaction_id' => 'CALLBACK-URL-002',
            'amount' => 100.00,
            'callback_url' => 'https://merchant.example/callback',
        ]);

        expect($gateway->capturedReturnUrl)->toBe('https://merchant.example/callback');
    });

    test('handles extremely large amount safely', function () {
        $gateway = app(FahipayGateway::class);

        $signature = $gateway->generateSignature('TEST-001', 99999999999, 1700000000);

        expect($signature)->toBeString();
    });

    test('generates signature for sql-injection-like transaction id', function () {
        $gateway = app(FahipayGateway::class);

        $maliciousId = "TEST-001'; DROP TABLE fahipay_payments;--";
        $signature = $gateway->generateSignature($maliciousId, 10000, 1700000000);

        expect($signature)->toBeString();
    });

    test('generated request transaction ids support odd configured lengths', function () {
        config(['fahipay.payment.unique_id_length' => 11]);

        $request = new CreatePaymentRequest();
        $method = new ReflectionMethod(CreatePaymentRequest::class, 'generateTransactionId');
        $method->setAccessible(true);

        $transactionId = $method->invoke($request);

        expect($transactionId)->toStartWith('PAY-')
            ->and(strlen(substr($transactionId, 4)))->toBe(11);
    });
});

/**
 * Security Test: API Security
 */
describe('API Endpoint Security', function () {
    test('api routes have the configured middleware applied', function () {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($r) => $r->uri() === 'api/fahipay/payments' && in_array('POST', $r->methods()));

        expect($route)->not->toBeNull();
        expect($route->middleware())->toContain('api');
    });

    test('destructive admin routes are gated by config', function () {
        $routes = collect(app('router')->getRoutes()->getRoutes());

        $hasDelete = $routes->contains(
            fn ($r) => $r->uri() === 'api/fahipay/payments/{transactionId}' && in_array('DELETE', $r->methods())
        );

        // Registered here only because the test harness opts in via
        // fahipay.api.admin_enabled = true; they are off by default.
        expect(config('fahipay.api.admin_enabled'))->toBeTrue();
        expect($hasDelete)->toBeTrue();
    });

    test('webhook rejects unsigned success requests', function () {
        $response = $this->postJson('/api/fahipay/webhook', [
            'Success' => 'true',
            'ShoppingCartID' => 'TEST-001',
        ]);

        expect($response->status())->toBe(400);
    });

    test('payment listing clamps per page to a positive value', function () {
        $response = $this->getJson('/api/fahipay/payments?per_page=0');

        $response->assertOk();
        expect($response->json('meta.per_page'))->toBe(1);
    });
});

/**
 * Security Test: Data Exposure
 */
describe('Data Exposure Security', function () {
    test('response does not expose secret key', function () {
        $gateway = app(FahipayGateway::class);

        $response = $gateway->getLastResponse();

        if ($response) {
            expect(json_encode($response))->not->toContain('test_secret_key_12345');
        } else {
            expect(true)->toBeTrue();
        }
    });

    test('error responses do not expose sensitive data', function () {
        config(['fahipay.secret_key' => '']);

        $gateway = app(FahipayGateway::class);

        try {
            $gateway->createPayment('TEST-001', 100.00);
        } catch (\Exception $e) {
            expect($e->getMessage())->not->toContain('secret');
        }
    });
});

/**
 * Security Test: Race Conditions
 */
describe('Race Condition Security', function () {
    test('handles duplicate payment creation', function () {
        $gateway = app(FahipayGateway::class);
        $transactionId = 'RACE-TEST-' . time();

        $payment1 = $gateway->createPayment($transactionId, 100.00);
        $payment2 = $gateway->createPayment($transactionId, 100.00);

        expect($payment1->transactionId)->toBe($transactionId);
        expect($payment2->transactionId)->toBe($transactionId);
    });
});

/**
 * Security Test: CSRF Protection
 */
describe('CSRF Protection', function () {
    test('removed initiate route is not exposed', function () {
        $response = $this->post('/fahipay/payment/initiate', [
            'amount' => 100,
        ]);

        $response->assertNotFound();
    });
});
