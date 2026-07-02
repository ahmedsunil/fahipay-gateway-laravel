<?php

namespace Fahipay\Gateway\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HandleCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'Success' => ['required', 'string', Rule::in(['true', 'false', '1', '0'])],
            'ShoppingCartID' => ['required', 'string', 'max:100'],
            'ApprovalCode' => ['nullable', 'string', 'max:100'],
            'Signature' => ['nullable', 'string', 'max:500'],
            'Message' => ['nullable', 'string', 'max:500'],
            'ErrorMessage' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $gateway = app(\Fahipay\Gateway\FahipayGateway::class);

            if (!$gateway->validateCallback($this)) {
                $validator->errors()->add('Signature', 'Invalid signature');
            }
        });
    }

    public function isSuccess(): bool
    {
        return $this->Success === 'true' || $this->Success === '1';
    }

    public function getTransactionId(): string
    {
        return $this->ShoppingCartID;
    }

    public function getApprovalCode(): ?string
    {
        return $this->ApprovalCode;
    }
}
