<?php

namespace Fahipay\Gateway\Http\Controllers\Api;

use Fahipay\Gateway\Actions\ProcessCallbackAction;
use Fahipay\Gateway\Facades\FahipayGateway;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        protected ProcessCallbackAction $processCallback
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $transactionId = $request->get('ShoppingCartID');
            $success = in_array($request->get('Success'), ['true', '1'], true);
            $approvalCode = $request->get('ApprovalCode');

            if (! FahipayGateway::validateStateChangingCallback($request)) {
                Log::warning('FahiPay webhook: Invalid or unsigned state-changing callback', [
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'error' => 'Invalid signature',
                ], 400);
            }

            $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

            if (! $payment) {
                Log::warning('FahiPay webhook: Transaction not found', [
                    'transaction_id' => $transactionId,
                ]);

                return response()->json([
                    'error' => 'Transaction not found',
                ], 404);
            }

            if ($success) {
                $payment->markAsCompleted($approvalCode);
                Log::info('FahiPay webhook: Payment completed', [
                    'transaction_id' => $transactionId,
                    'approval_code' => $approvalCode,
                ]);
            } else {
                $errorMessage = $request->get('ErrorMessage', $request->get('Message', 'Payment failed'));
                $payment->markAsFailed($errorMessage);
                Log::warning('FahiPay webhook: Payment failed', [
                    'transaction_id' => $transactionId,
                    'reason' => $errorMessage,
                ]);
            }

            return response()->json([
                'status' => 'success',
                'transaction_id' => $transactionId,
            ]);
        } catch (\Exception $e) {
            Log::error('FahiPay webhook error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Internal server error',
            ], 500);
        }
    }
}
