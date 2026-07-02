<?php

namespace Fahipay\Gateway;

use Fahipay\Gateway\Contracts\GatewayInterface;
use Fahipay\Gateway\Models\FahipayPayment;
use Fahipay\Gateway\Observers\FahipayPaymentObserver;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FahipayGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/fahipay.php', 'fahipay');

        $this->app->singleton(FahipayGateway::class, function ($app) {
            return new FahipayGateway;
        });

        $this->app->singleton(GatewayInterface::class, FahipayGateway::class);

        $this->app->alias(FahipayGateway::class, 'fahipay-gateway');

        $this->registerHelpers();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
            $this->publishViews();
            $this->registerCommands();
        }

        $this->registerRoutes();
        $this->registerEvents();
        $this->registerObservers();
        $this->registerViews();
        $this->registerTranslations();
        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        if (! class_exists(\Livewire\Livewire::class)) {
            return;
        }

        \Livewire\Livewire::component('fahipay-pay-button', Livewire\PayButton::class);
        \Livewire\Livewire::component('fahipay-payment-modal', Livewire\PaymentModal::class);
    }

    protected function registerHelpers(): void
    {
        $helpers = __DIR__.'/../src/Support/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../config/fahipay.php' => config_path('fahipay.php'),
        ], 'fahipay-config');
    }

    protected function publishMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'fahipay-migrations');
    }

    protected function publishViews(): void
    {
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/fahipay'),
        ], 'fahipay-views');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            Console\InstallCommand::class,
            Console\CreatePaymentCommand::class,
            Console\CheckPaymentCommand::class,
        ]);
    }

    protected function registerRoutes(): void
    {
        if ($this->app['config']->get('fahipay.routes.enabled', true)) {
            Route::group([
                'prefix' => $this->app['config']->get('fahipay.routes.prefix', 'fahipay'),
                'middleware' => $this->app['config']->get('fahipay.routes.middleware', ['web']),
            ], function () {
                require __DIR__.'/../routes/web.php';
            });
        }

        if ($this->app['config']->get('fahipay.api.enabled', false)) {
            Route::prefix($this->app['config']->get('fahipay.api.prefix', 'api/fahipay'))
                ->middleware($this->app['config']->get('fahipay.api.middleware', ['api', 'auth']))
                ->group(function () {
                    require __DIR__.'/../routes/api.php';
                });
        }
    }

    protected function registerEvents(): void
    {
        $events = $this->app['config']->get('fahipay.events', []);

        foreach ($events as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    protected function registerTranslations(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'fahipay');
    }

    protected function registerViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'fahipay');
    }

    protected function registerObservers(): void
    {
        FahipayPayment::observe(FahipayPaymentObserver::class);
    }
}
