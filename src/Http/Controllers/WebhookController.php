<?php

namespace Fahipay\Gateway\Http\Controllers;

use Fahipay\Gateway\Events\PaymentCancelledEvent;
use Fahipay\Gateway\Events\PaymentFailedEvent;
use Fahipay\Gateway\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected FahipayGateway $gateway
    ) {}

    public function handle(Request $request)
    {
        try {
            $transaction = $this->gateway->handleCallback($request);

            return response()->json([
                'status' => 'success',
                'transaction_id' => $transaction->transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('FahiPay webhook error: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Callback could not be processed',
            ], 400);
        }
    }

    public function callback(Request $request)
    {
        return $this->handle($request);
    }

    public function success(Request $request)
    {
        if (! $this->gateway->validateCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $success = in_array($request->get('Success'), ['true', '1'], true);

        if ($success) {
            $transaction = $this->gateway->handleCallback($request);

            return view('fahipay::success', [
                'transactionId' => $transaction->transactionId,
                'approvalCode' => $transaction->approvalCode,
            ]);
        }

        return view('fahipay::error', [
            'transactionId' => $request->get('ShoppingCartID'),
            'message' => 'Payment was not completed',
        ]);
    }

    public function cancel(Request $request)
    {
        if (! $this->gateway->validateDisplayCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $transactionId = $request->get('ShoppingCartID');

        if ($this->gateway->validateStateChangingCallback($request)) {
            $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

            if ($payment) {
                $payment->markAsCancelled();
            } else {
                event(new PaymentCancelledEvent($transactionId));
            }
        }

        return view('fahipay::cancelled', [
            'transactionId' => $transactionId,
        ]);
    }

    public function error(Request $request)
    {
        if (! $this->gateway->validateDisplayCallback($request)) {
            return view('fahipay::error', [
                'message' => 'Invalid signature',
            ]);
        }

        $transactionId = $request->get('ShoppingCartID');
        $message = $request->get('ErrorMessage', $request->get('Message', 'Payment failed'));

        if ($this->gateway->validateStateChangingCallback($request)) {
            $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

            if ($payment) {
                $payment->markAsFailed($message);
            } else {
                event(new PaymentFailedEvent($transactionId, $message));
            }
        }

        return view('fahipay::error', [
            'transactionId' => $transactionId,
            'message' => $message,
        ]);
    }
}
