<?php

namespace Fahipay\Gateway\Jobs;

use Fahipay\Gateway\Actions\VerifyPaymentAction;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RetryFailedPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 120;

    public function __construct(
        public string $transactionId
    ) {}

    public function handle(VerifyPaymentAction $verifyPayment): void
    {
        $payment = FahipayPayment::where('transaction_id', $this->transactionId)->first();

        if (! $payment || $payment->status->value !== 'failed') {
            Log::info('Skipping retry - payment not in failed state', [
                'transaction_id' => $this->transactionId,
            ]);

            return;
        }

        try {
            $transaction = $verifyPayment->execute($this->transactionId);

            if ($transaction->isSuccessful()) {
                $payment->markAsCompleted($transaction->approvalCode);
                Log::info('Payment recovered via retry', [
                    'transaction_id' => $this->transactionId,
                ]);
            } elseif ($transaction->isPending()) {
                Log::info('Payment still pending', [
                    'transaction_id' => $this->transactionId,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Retry check failed', [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment retry job failed', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
