<?php

namespace Fahipay\Gateway\Mail;

use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceivedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        protected FahipayPayment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment Confirmation - '.$this->payment->transaction_id,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'fahipay::mail.payment-received',
            with: [
                'payment' => $this->payment,
            ],
        );
    }
}
