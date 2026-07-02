<?php

namespace Fahipay\Gateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentInitiatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        public string $transactionId,
        public float $amount,
        public array $response
    ) {}
}
