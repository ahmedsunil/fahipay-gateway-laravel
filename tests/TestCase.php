<?php

use Fahipay\Gateway\FahipayGatewayServiceProvider;
use Orchestra\Testbench\TestCase;

abstract class TestCaseBase extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [FahipayGatewayServiceProvider::class];
    }

    protected function getPackageAliases($app)
    {
        return [
            'FahipayGateway' => Fahipay\Gateway\Facades\FahipayGateway::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('fahipay', [
            'shop_id' => 'test_shop',
            'secret_key' => 'test_secret_key',
            'test_mode' => true,
            'return_url' => 'http://localhost/callback',
            'cancel_url' => 'http://localhost/cancel',
            'error_url' => 'http://localhost/error',
            'base_url' => 'https://test.fahipay.mv/api/merchants',
            'web_url' => 'https://test.fahipay.mv',
            'test_web_url' => 'https://test.fahipay.mv',
            'payment' => [
                'prefix' => 'PAY',
                'unique_id_length' => 12,
                'expire_hours' => 24,
                'expire_without_verification' => false,
            ],
            'routes' => [
                'enabled' => true,
                'prefix' => 'fahipay',
                'middleware' => ['web'],
            ],
            'api' => [
                'enabled' => true,
                'prefix' => 'api/fahipay',
                'middleware' => ['api'],
                'webhook_middleware' => ['api'],
                // Admin routes default to OFF in production; enabled here (with
                // no extra middleware) so the destructive endpoints can be
                // exercised by the test suite.
                'admin_enabled' => true,
                'admin_middleware' => [],
            ],
            'database' => [
                'enabled' => true,
                'connection' => null,
            ],
            'allowed_redirect_hosts' => [],
            'allow_unrestricted_callback_urls' => false,
        ]);
    }
}
