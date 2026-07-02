<?php

namespace Fahipay\Gateway\Observers;

use Fahipay\Gateway\Models\FahipayPayment;
use Fahipay\Gateway\Enums\PaymentStatus;

class FahipayPaymentObserver
{
    public function created(FahipayPayment $payment): void
    {
        \Illuminate\Support\Facades\Log::info('FahiPay: Payment created', [
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

            \Illuminate\Support\Facades\Log::info('FahiPay: Payment status changed', [
                'transaction_id' => $payment->transaction_id,
                'old_status' => $oldStatus->value,
                'new_status' => $newStatus->value,
            ]);

            if ($newStatus === PaymentStatus::COMPLETED && $oldStatus !== PaymentStatus::COMPLETED) {
                event(new \Fahipay\Gateway\Events\PaymentCompletedEvent(
                    $payment->transaction_id,
                    $payment->approval_code
                ));
            } elseif ($newStatus === PaymentStatus::FAILED && $oldStatus !== PaymentStatus::FAILED) {
                event(new \Fahipay\Gateway\Events\PaymentFailedEvent(
                    $payment->transaction_id,
                    $payment->error_message
                ));
            } elseif ($newStatus === PaymentStatus::CANCELLED && $oldStatus !== PaymentStatus::CANCELLED) {
                event(new \Fahipay\Gateway\Events\PaymentCancelledEvent(
                    $payment->transaction_id
                ));
            }
        }
    }

    public function deleted(FahipayPayment $payment): void
    {
        \Illuminate\Support\Facades\Log::warning('FahiPay: Payment deleted', [
            'transaction_id' => $payment->transaction_id,
        ]);
    }
}
