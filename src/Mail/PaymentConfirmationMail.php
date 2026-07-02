<?php

namespace Fahipay\Gateway\Mail;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected FahipayPayment $payment,
        protected ?string $customerEmail = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Received - '.$this->payment->transaction_id,
            to: $this->customerEmail !== null ? [$this->customerEmail] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'fahipay::mail.payment-confirmation',
            with: [
                'payment' => $this->payment,
            ],
        );
    }
}
