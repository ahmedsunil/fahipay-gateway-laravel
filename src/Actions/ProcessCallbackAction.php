<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCancelledEvent;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProcessCallbackAction
{
    public function execute(Request $request): TransactionData
    {
        return FahipayGateway::handleCallback($request);
    }

    public function handleCancellation(Request $request): TransactionData
    {
        if (!FahipayGateway::validateStateChangingCallback($request)) {
            Log::warning('FahiPay: Invalid cancellation signature', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
            throw new FahipayException('Invalid signature');
        }

        $transactionId = $request->get('ShoppingCartID');
        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if ($payment) {
            $payment->markAsCancelled();
        } else {
            event(new PaymentCancelledEvent($transactionId));
        }

        Log::info("FahiPay: Payment cancelled", ['transaction_id' => $transactionId]);

        return new TransactionData(
            transactionId: $transactionId,
            amount: 0,
            status: PaymentStatus::CANCELLED,
            rawResponse: $request->all()
        );
    }
}
