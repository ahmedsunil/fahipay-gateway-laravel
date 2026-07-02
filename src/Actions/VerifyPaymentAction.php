<?php

namespace Fahipay\Gateway\Actions;

use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Facades\FahipayGateway;

class VerifyPaymentAction
{
    public function execute(string $transactionId): TransactionData
    {
        $transaction = FahipayGateway::getTransaction($transactionId);

        if (! $transaction) {
            throw new FahipayException("Transaction not found: {$transactionId}");
        }

        return $transaction;
    }

    public function isSuccessful(string $transactionId): bool
    {
        $transaction = $this->execute($transactionId);

        return $transaction->isSuccessful();
    }

    public function isPending(string $transactionId): bool
    {
        $transaction = $this->execute($transactionId);

        return $transaction->isPending();
    }

    public function isFailed(string $transactionId): bool
    {
        $transaction = $this->execute($transactionId);

        return $transaction->isFailed();
    }
}
