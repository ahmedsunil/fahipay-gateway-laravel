<?php

namespace Fahipay\Gateway\Livewire;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PaymentModal extends Component
{
    public bool $showModal = false;
    // Locked: these values are set server-side and must not be mutable from the
    // client before creating a payment.
    #[Locked]
    public string $transactionId = '';
    #[Locked]
    public float $amount = 0;
    public ?string $description = null;
    public ?string $customerEmail = null;
    public bool $isLoading = false;
    public ?string $paymentUrl = null;
    public ?string $errorMessage = null;
    public string $modalTitle = 'Complete Payment';
    public string $submitText = 'Pay Now';
    public bool $showAmount = true;
    public bool $showDescription = false;

    protected function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'transaction_id' => 'nullable|string|max:100|regex:/^[A-Za-z0-9\-_]+$/',
            'description' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
        ];
    }

    public function mount(): void
    {
        if (empty($this->transactionId)) {
            $this->generateTransactionId();
        }
    }

    public function openModal(): void
    {
        $this->showModal = true;
        if (empty($this->transactionId)) {
            $this->generateTransactionId();
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['paymentUrl', 'errorMessage']);
    }

    public function generateTransactionId(): void
    {
        $prefix = config('fahipay.payment.prefix', 'PAY');
        $length = (int) config('fahipay.payment.unique_id_length', 12);
        $this->transactionId = $prefix . '-' . Str::random(max(1, $length));
    }

    public function initiatePayment(): void
    {
        $this->validate();
        $this->isLoading = true;
        $this->errorMessage = null;

        try {
            $metadata = [];
            if ($this->customerEmail) {
                $metadata['customer_email'] = $this->customerEmail;
            }

            $payment = FahipayGateway::createPayment(
                $this->transactionId,
                $this->amount,
                $this->description,
                $metadata
            );

            if ($payment->paymentUrl) {
                $this->paymentUrl = $payment->paymentUrl;
                $this->redirect($payment->paymentUrl);
            } else {
                $this->errorMessage = $payment->rawResponse['message'] ?? 'Failed to create payment';
            }
        } catch (\Exception $e) {
            $this->errorMessage = $e->getMessage();
        } finally {
            $this->isLoading = false;
        }
    }

    public function render()
    {
        return view('fahipay::livewire.payment-modal');
    }
}
