<?php

namespace Fahipay\Gateway\Jobs;

use Fahipay\Gateway\Actions\VerifyPaymentAction;
use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpirePendingPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $transactionId = null
    ) {}

    public function handle(VerifyPaymentAction $verifyPayment): void
    {
        $hours = config('fahipay.payment.expire_hours', 24);
        $cutoff = now()->subHours($hours);

        $query = FahipayPayment::pending()
            ->where('created_at', '<', $cutoff);

        if ($this->transactionId) {
            $query->where('transaction_id', $this->transactionId);
        }

        $payments = $query->get();
        $expiredCount = 0;
        $recoveredCount = 0;
        $skippedCount = 0;

        foreach ($payments as $payment) {
            try {
                $transaction = $verifyPayment->execute($payment->transaction_id);

                if ($transaction->isSuccessful()) {
                    $payment->markAsCompleted($transaction->approvalCode);
                    $recoveredCount++;
                    Log::info('Expired pending payment recovered as completed', [
                        'transaction_id' => $payment->transaction_id,
                    ]);

                    continue;
                }

                if ($transaction->isPending() || $transaction->status === PaymentStatus::UNKNOWN) {
                    $skippedCount++;
                    Log::info('Skipping pending payment expiry after gateway check', [
                        'transaction_id' => $payment->transaction_id,
                        'status' => $transaction->status->value,
                    ]);

                    continue;
                }

                $payment->markAsFailed('Payment expired');
                $expiredCount++;
                Log::info('Pending payment expired', [
                    'transaction_id' => $payment->transaction_id,
                ]);
            } catch (\Throwable $e) {
                if (config('fahipay.payment.expire_without_verification', false)) {
                    $payment->markAsFailed('Payment expired');
                    $expiredCount++;
                    Log::warning('Pending payment expired without gateway verification', [
                        'transaction_id' => $payment->transaction_id,
                        'error' => $e->getMessage(),
                    ]);

                    continue;
                }

                $skippedCount++;
                Log::warning('Skipping pending payment expiry because verification failed', [
                    'transaction_id' => $payment->transaction_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Processed expired pending payments', [
            'expired' => $expiredCount,
            'recovered' => $recoveredCount,
            'skipped' => $skippedCount,
        ]);
    }
}
