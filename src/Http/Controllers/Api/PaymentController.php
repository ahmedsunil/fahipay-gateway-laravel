<?php

namespace Fahipay\Gateway\Http\Controllers\Api;

use Fahipay\Gateway\Actions\CreatePaymentAction;
use Fahipay\Gateway\Actions\VerifyPaymentAction;
use Fahipay\Gateway\Http\Requests\CreatePaymentRequest;
use Fahipay\Gateway\Http\Resources\PaymentResource;
use Fahipay\Gateway\Http\Resources\TransactionResource;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PaymentController extends Controller
{
    public function __construct(
        protected CreatePaymentAction $createPayment,
        protected VerifyPaymentAction $verifyPayment
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min($request->integer('per_page', 15), 100));

        $payments = FahipayPayment::query()
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->when($request->merchant_id, fn ($q, $id) => $q->where('merchant_id', $id))
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'data' => PaymentResource::collection($payments),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    public function store(CreatePaymentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (FahipayPayment::where('transaction_id', $validated['transaction_id'])->exists()) {
            return response()->json([
                'error' => 'Payment transaction already exists',
            ], 409);
        }

        $payment = $this->createPayment->execute($validated);

        if (! $payment->paymentUrl) {
            return response()->json([
                'error' => $payment->rawResponse['message'] ?? $payment->rawResponse['msg'] ?? 'Payment creation failed',
                'data' => [
                    'transaction_id' => $payment->transactionId,
                    'amount' => $payment->amount,
                    'status' => $payment->status->value,
                ],
            ], 422);
        }

        FahipayPayment::createPayment(
            $validated['transaction_id'],
            (string) config('fahipay.shop_id'),
            $validated['amount'],
            $validated['description'] ?? null,
            $validated['metadata'] ?? null
        );

        return response()->json([
            'data' => [
                'transaction_id' => $payment->transactionId,
                'amount' => $payment->amount,
                'status' => $payment->status->value,
                'payment_url' => $payment->paymentUrl,
            ],
            'message' => 'Payment created successfully',
        ], 201);
    }

    public function show(Request $request, string $transactionId): JsonResponse
    {
        if (! $this->isValidTransactionId($transactionId)) {
            return response()->json([
                'error' => 'Invalid transaction ID format',
            ], 400);
        }

        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (! $payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function verify(string $transactionId): JsonResponse
    {
        if (! $this->isValidTransactionId($transactionId)) {
            return response()->json([
                'error' => 'Invalid transaction ID format',
            ], 400);
        }

        try {
            $transaction = $this->verifyPayment->execute($transactionId);

            return response()->json([
                'data' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function update(Request $request, string $transactionId): JsonResponse
    {
        if (! $this->isValidTransactionId($transactionId)) {
            return response()->json([
                'error' => 'Invalid transaction ID format',
            ], 400);
        }

        $request->validate([
            'description' => 'sometimes|string|max:255',
            'metadata' => 'sometimes|array',
        ]);

        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (! $payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        $payment->update($request->only(['description', 'metadata']));

        return response()->json([
            'data' => new PaymentResource($payment),
            'message' => 'Payment updated successfully',
        ]);
    }

    public function destroy(string $transactionId): JsonResponse
    {
        if (! $this->isValidTransactionId($transactionId)) {
            return response()->json([
                'error' => 'Invalid transaction ID format',
            ], 400);
        }

        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if (! $payment) {
            return response()->json([
                'error' => 'Payment not found',
            ], 404);
        }

        $payment->delete();

        return response()->json([
            'message' => 'Payment deleted successfully',
        ]);
    }

    protected function isValidTransactionId(string $transactionId): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9\-_]{1,100}$/', $transactionId);
    }
}
