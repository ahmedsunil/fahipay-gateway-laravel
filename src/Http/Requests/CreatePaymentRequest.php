<?php

namespace Fahipay\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'transaction_id' => ['sometimes', 'string', 'max:100', 'regex:/^[A-Za-z0-9\-_]+$/'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'metadata' => ['sometimes', 'array'],
            'metadata.*' => ['sometimes', 'string', 'max:1000'],
            'callback_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'currency' => ['sometimes', 'string', 'size:3', Rule::in(['MVR', 'USD'])],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'The minimum payment amount is 0.01 MVR.',
            'amount.max' => 'The maximum payment amount is 999999.99 MVR.',
            'callback_url.url' => 'Please provide a valid callback URL.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        if (! isset($data['transaction_id'])) {
            $data['transaction_id'] = $this->generateTransactionId();
        }

        if (! isset($data['currency'])) {
            $data['currency'] = 'MVR';
        }

        return $data;
    }

    protected function generateTransactionId(): string
    {
        $prefix = config('fahipay.payment.prefix', 'PAY');
        $length = max(1, (int) config('fahipay.payment.unique_id_length', 12));

        return $prefix.'-'.substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}
