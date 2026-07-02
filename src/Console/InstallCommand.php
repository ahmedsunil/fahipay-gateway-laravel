<?php

namespace Fahipay\Gateway\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'fahipay:install';

    protected $description = 'Install FahiPay Gateway package';

    public function handle(): int
    {
        $this->info('Installing FahiPay Gateway...');

        $this->call('vendor:publish', [
            '--provider' => 'Fahipay\Gateway\FahipayGatewayServiceProvider',
            '--tag' => 'fahipay-config',
        ]);

        $this->call('vendor:publish', [
            '--provider' => 'Fahipay\Gateway\FahipayGatewayServiceProvider',
            '--tag' => 'fahipay-migrations',
        ]);

        $this->info('FahiPay Gateway installed successfully!');
        $this->info('Please configure your credentials in .env file:');
        $this->line('FAHIPAY_MERCHANT_ID=your_merchant_id');
        $this->line('FAHIPAY_SECRET_KEY=your_secret_key');
        $this->line('FAHIPAY_TEST_MODE=true');

        return self::SUCCESS;
    }
}
