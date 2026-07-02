<?php

namespace Fahipay\Gateway\Facades;

use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\FahipayGateway as Gateway;
use Illuminate\Support\Facades\Facade;

/**
 * @method static PaymentData createPayment(string $transactionId, float $amount, ?string $description = null, ?array $metadata = [])
 * @method static PaymentData create(array $config)
 * @method static string getPaymentUrl(string $transactionId, float $amount)
 * @method static TransactionData|null getTransaction(string $transactionId)
 * @method static TransactionData|null getPayment(string $transactionId)
 * @method static string generateCallbackSignature(string $success, string $transactionId, ?string $approvalCode, ?int $timestamp = null)
 * @method static bool verifySignature(string $success, string $transactionId, ?string $approvalCode, int|string $signatureOrTimestamp, ?string $signature = null)
 * @method static bool validateCallback(\Illuminate\Http\Request $request)
 * @method static bool validateDisplayCallback(\Illuminate\Http\Request $request)
 * @method static bool validateStateChangingCallback(\Illuminate\Http\Request $request)
 * @method static TransactionData handleCallback(\Illuminate\Http\Request $request)
 * @method static TransactionData processWebhook(\Illuminate\Http\Request $request)
 * @method static string getLink(string $transactionId, float $amount)
 * @method static string generateSignature(string $transactionId, int $amountInCents, ?int $timestamp = null)
 * @method static Gateway setMerchantId(string $merchantId)
 * @method static Gateway setSecretKey(string $secretKey)
 * @method static Gateway setTestMode(bool $testMode = true)
 * @method static Gateway setReturnUrl(?string $url)
 * @method static ?string getReturnUrl()
 * @method static Gateway setCancelUrl(string $url)
 * @method static Gateway setErrorUrl(string $url)
 * @method static bool isTestMode()
 * @method static bool isConfigured()
 * @method static ?string getLastTransactionId()
 * @method static ?array getLastResponse()
 * 
 * @see \Fahipay\Gateway\FahipayGateway
 */
class FahipayGateway extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Gateway::class;
    }
}
