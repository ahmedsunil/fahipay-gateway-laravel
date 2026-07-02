<?php

namespace Fahipay\Gateway\Jobs;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public string $transactionId,
        public float $amount,
        public ?string $description = null,
        public ?array $metadata = null
    ) {}

    public function handle(): void
    {
        Log::info('Processing payment', ['transaction_id' => $this->transactionId]);

        $payment = FahipayGateway::createPayment(
            $this->transactionId,
            $this->amount,
            $this->description,
            $this->metadata
        );

        if ($payment->paymentUrl) {
            Log::info('Payment created successfully', [
                'transaction_id' => $this->transactionId,
                'url' => $payment->paymentUrl,
            ]);
        } else {
            Log::error('Failed to create payment', [
                'transaction_id' => $this->transactionId,
                'response' => $payment->rawResponse,
            ]);

            throw new \Exception('Failed to create payment');
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Payment processing failed permanently', [
            'transaction_id' => $this->transactionId,
            'error' => $exception->getMessage(),
        ]);
    }
}
