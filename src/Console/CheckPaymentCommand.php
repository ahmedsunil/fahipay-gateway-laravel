<?php

namespace Fahipay\Gateway\Console;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Console\Command;

class CheckPaymentCommand extends Command
{
    protected $signature = 'fahipay:check {transaction_id : The transaction ID to check}';

    protected $description = 'Check FahiPay payment status';

    public function handle(): int
    {
        $transactionId = $this->argument('transaction_id');

        $this->info("Checking payment status for: {$transactionId}");

        $transaction = FahipayGateway::getTransaction($transactionId);

        if ($transaction) {
            $this->info('Payment found!');
            $this->line("Status: {$transaction->status->label()}");
            $this->line("Amount: MVR {$transaction->getAmountFormatted()}");
            $this->line('Method: '.($transaction->method ?? 'N/A'));
            $this->line('Approval Code: '.($transaction->approvalCode ?? 'N/A'));

            if ($transaction->time) {
                $this->line("Time: {$transaction->time->format('Y-m-d H:i:s')}");
            }
        } else {
            $this->error('Payment not found');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
