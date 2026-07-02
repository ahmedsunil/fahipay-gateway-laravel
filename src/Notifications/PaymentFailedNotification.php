<?php

namespace Fahipay\Gateway\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $transactionId,
        protected ?string $reason = null
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
            ->subject('Payment Failed - '.$this->transactionId)
            ->greeting('Hello!')
            ->line('Your payment could not be completed.')
            ->line('Transaction ID: '.$this->transactionId)
            ->line('Reason: '.($this->reason ?? 'Unknown error'))
            ->line('Please try again or contact support.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'status' => 'failed',
            'message' => $this->reason ?? 'Payment failed',
        ];
    }
}
