<?php

namespace Fahipay\Gateway\Notifications;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected FahipayPayment $payment
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment Received - '.$this->payment->transaction_id)
            ->greeting('Hello!')
            ->line('We have received your payment.')
            ->line('Transaction ID: '.$this->payment->transaction_id)
            ->line('Amount: MVR '.number_format((float) $this->payment->amount, 2))
            ->line('Status: '.$this->payment->status->label())
            ->line('Thank you for your payment!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->payment->transaction_id,
            'amount' => $this->payment->amount,
            'status' => $this->payment->status->value,
            'message' => 'Payment received successfully',
        ];
    }
}
