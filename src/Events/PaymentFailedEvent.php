<?php

namespace Fahipay\Gateway\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentFailedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $transactionId,
        public ?string $reason = null
    ) {}
}
