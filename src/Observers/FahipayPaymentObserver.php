<?php

namespace Fahipay\Gateway\Observers;

use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCancelledEvent;
use Fahipay\Gateway\Events\PaymentCompletedEvent;
use Fahipay\Gateway\Events\PaymentFailedEvent;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Support\Facades\Log;

class FahipayPaymentObserver
{
    public function created(FahipayPayment $payment): void
    {
        Log::info('FahiPay: Payment created', [
            'transaction_id' => $payment->transaction_id,
            'amount' => $payment->amount,
            'status' => $payment->status->value,
        ]);
    }

    public function updated(FahipayPayment $payment): void
    {
        if ($payment->wasChanged('status')) {
            $originalStatus = $payment->getOriginal('status');
            $oldStatus = $originalStatus instanceof PaymentStatus
                ? $originalStatus
                : PaymentStatus::fromString((string) $originalStatus);
            $newStatus = $payment->status;

            Log::info('FahiPay: Payment status changed', [
                'transaction_id' => $payment->transaction_id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ]);

            if ($newStatus === PaymentStatus::COMPLETED && $oldStatus !== PaymentStatus::COMPLETED) {
                event(new PaymentCompletedEvent(
                    $payment->transaction_id,
                    $payment->approval_code
                ));
            } elseif ($newStatus === PaymentStatus::FAILED && $oldStatus !== PaymentStatus::FAILED) {
                event(new PaymentFailedEvent(
                    $payment->transaction_id,
                    $payment->error_message
                ));
            } elseif ($newStatus === PaymentStatus::CANCELLED && $oldStatus !== PaymentStatus::CANCELLED) {
                event(new PaymentCancelledEvent(
                    $payment->transaction_id
                ));
            }
        }
    }

    public function deleted(FahipayPayment $payment): void
    {
        Log::warning('FahiPay: Payment deleted', [
            'transaction_id' => $payment->transaction_id,
        ]);
    }
}
