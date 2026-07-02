<?php

namespace Fahipay\Gateway\Livewire;

use Fahipay\Gateway\Facades\FahipayGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Component;

class PayButton extends Component
{
    // Locked: amount and transaction id are set server-side at mount and must
    // never be mutable from the client, otherwise a buyer could pay a lower
    // amount or hijack another transaction by editing the Livewire payload.
    #[Locked]
    public string $transactionId = '';

    #[Locked]
    public float $amount = 0;

    public ?string $description = null;

    public ?string $redirectUrl = null;

    public bool $isLoading = false;

    public ?string $paymentUrl = null;

    public ?string $errorMessage = null;

    /**
     * @return array<string, string>
     */
    protected function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:999999.99',
            'transaction_id' => 'nullable|string|max:100|regex:/^[A-Za-z0-9\-_]+$/',
            'description' => 'nullable|string|max:255',
            'redirect_url' => 'nullable|url|max:500',
        ];
    }

    public function mount(): void
    {
        if (empty($this->transactionId)) {
            $this->generateTransactionId();
        }
    }

    public function generateTransactionId(): void
    {
        $prefix = config('fahipay.payment.prefix', 'PAY');
        $length = config('fahipay.payment.unique_id_length', 12);

        $this->transactionId = $prefix.'-'.Str::random($length);
    }

    protected function validateRedirectUrl(): bool
    {
        if ($this->redirectUrl) {
            $parsed = parse_url($this->redirectUrl);
            $host = $parsed['host'] ?? '';

            $allowedHosts = config('fahipay.allowed_redirect_hosts', []);

            if (! empty($allowedHosts) && ! in_array($host, $allowedHosts)) {
                $this->errorMessage = 'Redirect URL not allowed';

                return false;
            }

            if (empty($allowedHosts) && ! config('fahipay.allow_unrestricted_callback_urls', false)) {
                $this->errorMessage = 'Redirect URL not allowed';

                return false;
            }

            if (! filter_var($this->redirectUrl, FILTER_VALIDATE_URL)) {
                $this->errorMessage = 'Invalid redirect URL';

                return false;
            }
        }

        return true;
    }

    public function initiatePayment(): void
    {
        $this->validate();

        if (! $this->validateRedirectUrl()) {
            $this->isLoading = false;

            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null;
        $previousReturnUrl = null;

        try {
            $gateway = FahipayGateway::getFacadeRoot();
            $previousReturnUrl = $gateway->getReturnUrl();

            if ($this->redirectUrl) {
                $gateway->setReturnUrl($this->redirectUrl);
            }

            $payment = $gateway->createPayment(
                $this->transactionId,
                $this->amount,
                $this->description
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
            if (isset($gateway) && $this->redirectUrl) {
                $gateway->setReturnUrl($previousReturnUrl);
            }

            $this->isLoading = false;
        }
    }

    public function render(): View
    {
        return view('fahipay::livewire.pay-button');
    }
}
