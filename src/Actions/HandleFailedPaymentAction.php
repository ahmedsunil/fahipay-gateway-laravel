<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Support\Facades\Log;

class HandleFailedPaymentAction
{
    public function execute(string $transactionId, ?string $reason = null): void
    {
        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if ($payment) {
            $payment->markAsFailed($reason);
            Log::info('FahiPay: Payment marked as failed in database', [
                'transaction_id' => $transactionId,
                'reason' => $reason,
            ]);
        }

        Log::warning('FahiPay: Payment failed', [
            'transaction_id' => $transactionId,
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<int, string>  $transactionIds
     */
    public function handleMultiple(array $transactionIds): int
    {
        $count = 0;

        foreach ($transactionIds as $transactionId) {
            $this->execute($transactionId);
            $count++;
        }

        return $count;
    }
}
