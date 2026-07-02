<?php

namespace Fahipay\Gateway\Console;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Console\Command;

class CreatePaymentCommand extends Command
{
    protected $signature = 'fahipay:create {transaction_id : The transaction ID} {amount : The payment amount} {--description= : Payment description}';

    protected $description = 'Create a new FahiPay payment';

    public function handle(): int
    {
        $transactionId = $this->argument('transaction_id');
        $amount = (float) $this->argument('amount');
        $description = $this->option('description');

        $this->info("Creating payment for transaction: {$transactionId}");

        $payment = FahipayGateway::createPayment($transactionId, $amount, $description);

        if ($payment->paymentUrl) {
            $this->info('Payment created successfully!');
            $this->line("Payment URL: {$payment->paymentUrl}");
            $this->line("Status: {$payment->status->value}");
        } else {
            $this->error('Failed to create payment');
            $this->line(json_encode($payment->rawResponse, JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
