<?php

namespace Fahipay\Gateway;

use Carbon\Carbon;
use Fahipay\Gateway\Contracts\GatewayInterface;
use Fahipay\Gateway\Data\PaymentData;
use Fahipay\Gateway\Data\TransactionData;
use Fahipay\Gateway\Enums\PaymentStatus;
use Fahipay\Gateway\Events\PaymentCompletedEvent;
use Fahipay\Gateway\Events\PaymentFailedEvent;
use Fahipay\Gateway\Events\PaymentInitiatedEvent;
use Fahipay\Gateway\Events\PaymentPendingEvent;
use Fahipay\Gateway\Exceptions\FahipayException;
use Fahipay\Gateway\Models\FahipayPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FahipayGateway implements GatewayInterface
{
    protected ?string $shopId = null;

    protected ?string $secretKey = null;

    protected string $baseUrl = 'https://fahipay.mv/api/merchants';

    protected string $webUrl = 'https://fahipay.mv';

    protected string $testBaseUrl = 'https://test.fahipay.mv/api/merchants';

    protected bool $testMode = false;

    protected ?string $apiKey = null;

    protected ?string $returnUrl = null;

    protected ?string $cancelUrl = null;

    protected ?string $errorUrl = null;

    protected ?string $lastTransactionId = null;

    protected ?array $lastResponse = null;

    protected array $config = [];

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->config = config('fahipay', []);

        $this->shopId = $this->config['shop_id'] ?? null;
        $this->secretKey = $this->config['secret_key'] ?? null;
        $this->apiKey = $this->config['api_key'] ?? null;
        $this->testMode = $this->config['test_mode'] ?? false;
        $this->returnUrl = $this->config['return_url'] ?? null;
        $this->cancelUrl = $this->config['cancel_url'] ?? null;
        $this->errorUrl = $this->config['error_url'] ?? null;

        $this->baseUrl = $this->testMode
            ? ($this->config['test_base_url'] ?? $this->testBaseUrl)
            : ($this->config['base_url'] ?? $this->baseUrl);
        $this->webUrl = $this->testMode
            ? ($this->config['test_web_url'] ?? 'https://test.fahipay.mv')
            : ($this->config['web_url'] ?? $this->webUrl);
    }

    public function setShopId(string $shopId): self
    {
        $this->shopId = $shopId;

        return $this;
    }

    /**
     * @deprecated Use setShopId() instead
     */
    public function setMerchantId(string $merchantId): self
    {
        $this->shopId = $merchantId;

        return $this;
    }

    /**
     * @deprecated Use getShopId() instead
     */
    public function getMerchantId(): ?string
    {
        return $this->getShopId();
    }

    public function getShopId(): ?string
    {
        return $this->shopId;
    }

    public function setSecretKey(string $secretKey): self
    {
        $this->secretKey = $secretKey;

        return $this;
    }

    public function setTestMode(bool $testMode = true): self
    {
        $this->testMode = $testMode;
        $this->baseUrl = $testMode
            ? ($this->config['test_base_url'] ?? $this->testBaseUrl)
            : ($this->config['base_url'] ?? 'https://fahipay.mv/api/merchants');
        $this->webUrl = $testMode
            ? ($this->config['test_web_url'] ?? 'https://test.fahipay.mv')
            : ($this->config['web_url'] ?? 'https://fahipay.mv');

        return $this;
    }

    public function setReturnUrl(?string $returnUrl): self
    {
        $this->returnUrl = $returnUrl;

        return $this;
    }

    public function getReturnUrl(): ?string
    {
        return $this->returnUrl;
    }

    public function setCancelUrl(string $cancelUrl): self
    {
        $this->cancelUrl = $cancelUrl;

        return $this;
    }

    public function setErrorUrl(string $errorUrl): self
    {
        $this->errorUrl = $errorUrl;

        return $this;
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function getWebUrl(): string
    {
        return $this->webUrl;
    }

    /**
     * Create a new payment transaction
     *
     * Uses the API endpoint: POST https://fahipay.mv/api/merchants/createTxn/
     */
    public function createPayment(string $transactionId, float $amount, ?string $description = null, ?array $metadata = []): PaymentData
    {
        // Amount must be in cents (last 2 digits = decimal places)
        // Example: 19.00 = 1900, 19.50 = 1950
        $amountInCents = $this->validatePaymentInput($transactionId, $amount);

        $signature = $this->generateSignature($transactionId, $amountInCents);

        $data = [
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ];

        if ($description) {
            $data['ShoppingCartDesc'] = $description;
        }

        $response = $this->makeRequest('/createTxn/', $data);

        $this->lastTransactionId = $transactionId;
        $this->lastResponse = $response;

        Event::dispatch(new PaymentInitiatedEvent(
            $transactionId,
            $amount,
            $response
        ));

        if (($response['type'] ?? '') === 'success') {
            Cache::put("fahipay_payment_{$transactionId}", [
                'amount' => $amount,
                'status' => 'pending',
                'created_at' => now()->toIso8601String(),
            ], now()->addHours(24));
        }

        return new PaymentData(
            transactionId: $transactionId,
            amount: $amount,
            status: PaymentStatus::PENDING,
            paymentUrl: $response['link'] ?? null,
            rawResponse: $response
        );
    }

    /**
     * Create payment with array config
     */
    public function create(array $config): PaymentData
    {
        return $this->createPayment(
            $config['transaction_id'],
            $config['amount'],
            $config['description'] ?? null,
            $config['metadata'] ?? []
        );
    }

    /**
     * Get payment URL for redirect
     * FahiPay requires a POST to /payment/ which returns a redirect to the payment page
     */
    public function getPaymentUrl(string $transactionId, float $amount): string
    {
        $amountInCents = $this->validatePaymentInput($transactionId, $amount);
        $signature = $this->generateSignature($transactionId, $amountInCents);

        $webUrl = rtrim($this->webUrl, '/').'/payment/';

        $params = [
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ];

        $cookiePath = storage_path('fahipay/cookies_'.uniqid().'.txt');
        if (! is_dir(dirname($cookiePath))) {
            mkdir(dirname($cookiePath), 0755, true);
        }

        try {
            $redirectUrl = $this->requestPaymentRedirect($webUrl, $params, $cookiePath);
        } finally {
            @unlink($cookiePath);
        }

        if ($redirectUrl && $this->isValidRedirectUrl($redirectUrl)) {
            return $redirectUrl;
        }

        throw new FahipayException('Unable to create FahiPay payment redirect');
    }

    protected function requestPaymentRedirect(string $webUrl, array $params, string $cookiePath): ?string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $webUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiePath);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) ($this->config['timeout'] ?? 30));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($response === false || ! in_array((int) ($info['http_code'] ?? 0), [301, 302], true)) {
            return null;
        }

        preg_match('/Location: (.*)/i', $response, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    /**
     * Validate redirect URL to prevent SSRF attacks
     */
    protected function isValidRedirectUrl(string $url): bool
    {
        $allowedHosts = [
            'fahipay.mv',
            'test.fahipay.mv',
            'www.fahipay.mv',
            'pay.fahipay.mv',
        ];

        $parsed = parse_url($url);

        if (empty($parsed['host'])) {
            return false;
        }

        return in_array($parsed['host'], $allowedHosts, true);
    }

    /**
     * Query transaction status
     * Uses the API: GET https://fahipay.mv/api/merchants/getTxn/?mref=<ShoppingCartID>
     */
    public function getTransaction(string $transactionId): ?TransactionData
    {
        $response = $this->makeRequest('/getTxn/', [
            'mref' => $transactionId,
        ], 'GET', $this->apiKey ? ['X-Api-Key' => $this->apiKey] : []);

        if (($response['type'] ?? '') === 'success' && isset($response['data'])) {
            return new TransactionData(
                transactionId: $response['data']['mref'] ?? $transactionId,
                amount: (float) ($response['data']['amount'] ?? 0),
                status: $this->mapStatus($response['data']['status'] ?? 'unknown'),
                method: $response['data']['method'] ?? null,
                approvalCode: $response['data']['ApprovalCode'] ?? null,
                time: isset($response['data']['time']) ? Carbon::parse($response['data']['time']) : null,
                rawResponse: $response
            );
        }

        return null;
    }

    /**
     * Query payment status by Approval Code
     */
    public function getPayment(string $transactionId): ?TransactionData
    {
        return $this->getTransaction($transactionId);
    }

    /**
     * Generate the signature FahiPay sends with a successful callback.
     */
    public function generateCallbackSignature(string $success, string $transactionId, ?string $approvalCode, ?int $timestamp = null): string
    {
        $successValue = ($success === 'true' || $success === '1') ? '1' : '0';

        $signatureData = $this->shopId.$this->secretKey
            .$transactionId.$this->secretKey
            .$successValue.$this->secretKey
            .($approvalCode ?? '').$this->secretKey;

        return base64_encode(sha1($signatureData, true));
    }

    /**
     * Verify callback signature
     * Uses the FahiPay-documented SHA1/base64 callback signature. The optional
     * fifth argument is accepted for backward compatibility with earlier
     * timestamped package versions and is ignored.
     */
    public function verifySignature(string $success, string $transactionId, ?string $approvalCode, int|string $signatureOrTimestamp, ?string $signature = null): bool
    {
        $actualSignature = $signature ?? (string) $signatureOrTimestamp;
        $expectedSignature = $this->generateCallbackSignature($success, $transactionId, $approvalCode);

        return hash_equals($expectedSignature, $actualSignature);
    }

    /**
     * Validate callback from FahiPay
     * This strict validator is for callbacks that can change merchant state.
     * Display-only browser returns can use validateDisplayCallback().
     */
    public function validateCallback(Request $request): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('FahiPay: Callback rejected, gateway is not configured');

            return false;
        }

        $success = $request->get('Success', 'false');
        $transactionId = $request->get('ShoppingCartID', '');
        $approvalCode = $request->get('ApprovalCode');
        $signature = $request->get('Signature', '');
        $isSuccess = $success === 'true' || $success === '1';

        if ($transactionId === '') {
            Log::warning('FahiPay: Callback rejected, missing transaction id');

            return false;
        }

        if ($signature === '') {
            Log::warning('FahiPay: Callback rejected, missing signature', [
                'transaction_id' => $transactionId,
            ]);

            return false;
        }

        if ($isSuccess && empty($approvalCode)) {
            Log::warning('FahiPay: Success callback rejected, missing approval code', [
                'transaction_id' => $transactionId,
            ]);

            return false;
        }

        return $this->verifySignature($success, $transactionId, $approvalCode, $signature);
    }

    /**
     * Validate browser-return callbacks that are only used for display.
     * Unsigned failed/cancel callbacks can be shown to the user, but must not
     * drive order state transitions or package events.
     */
    public function validateDisplayCallback(Request $request): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('FahiPay: Display callback rejected, gateway is not configured');

            return false;
        }

        $transactionId = $request->get('ShoppingCartID', '');
        if ($transactionId === '') {
            Log::warning('FahiPay: Display callback rejected, missing transaction id');

            return false;
        }

        if ($request->filled('Signature')) {
            return $this->validateCallback($request);
        }

        $success = $request->get('Success', 'false');

        return ! ($success === 'true' || $success === '1');
    }

    /**
     * Validate callbacks that can change local merchant state.
     */
    public function validateStateChangingCallback(Request $request): bool
    {
        return $this->validateCallback($request);
    }

    /**
     * Handle callback from FahiPay
     */
    public function handleCallback(Request $request): TransactionData
    {
        $success = $request->get('Success', 'false');
        $transactionId = $request->get('ShoppingCartID', '');
        $approvalCode = $request->get('ApprovalCode');

        if (! $this->validateStateChangingCallback($request)) {
            Log::error('FahiPay: Invalid or expired callback', [
                'transaction_id' => $transactionId,
            ]);

            throw new FahipayException('Invalid signature');
        }

        $status = ($success === 'true' || $success === '1') ? PaymentStatus::COMPLETED : PaymentStatus::FAILED;

        // Retrieve payment amount from cache for accurate transaction data
        $paymentData = Cache::get("fahipay_payment_{$transactionId}");
        $amount = 0;
        if ($paymentData && isset($paymentData['amount'])) {
            $amount = $paymentData['amount'];
        } elseif ($request->has('TotalAmount')) {
            // Fallback: try to get from callback request (in cents)
            $amount = (int) $request->get('TotalAmount', 0) / 100;
        }

        $payment = FahipayPayment::where('transaction_id', $transactionId)->first();

        if ($payment) {
            match ($status) {
                PaymentStatus::COMPLETED => $payment->markAsCompleted($approvalCode),
                PaymentStatus::FAILED => $payment->markAsFailed($request->get('ErrorMessage', $request->get('Message', 'Payment failed'))),
                default => null,
            };
        } else {
            $errorMessage = $request->get('ErrorMessage', $request->get('Message', 'Payment failed'));

            Event::dispatch(match ($status) {
                PaymentStatus::COMPLETED => new PaymentCompletedEvent($transactionId, $approvalCode),
                PaymentStatus::FAILED => new PaymentFailedEvent($transactionId, $errorMessage),
                default => new PaymentPendingEvent($transactionId),
            });
        }

        Cache::forget("fahipay_payment_{$transactionId}");

        return new TransactionData(
            transactionId: $transactionId,
            amount: $amount,
            status: $status,
            approvalCode: $approvalCode,
            rawResponse: $request->all()
        );
    }

    /**
     * Process webhook
     */
    public function processWebhook(Request $request): TransactionData
    {
        return $this->handleCallback($request);
    }

    /**
     * Generate payment link (for direct redirect)
     * Note: This method places sensitive data (ShopID, Signature) in query parameters.
     * This is required by the FahiPay API specification. Consider using POST-based
     * payment flows for better security if the API supports it.
     */
    public function getLink(string $transactionId, float $amount): string
    {
        // Amount in cents
        $amountInCents = $this->validatePaymentInput($transactionId, $amount);
        $signature = $this->generateSignature($transactionId, $amountInCents);

        $params = http_build_query([
            'ShopID' => $this->shopId,
            'ShoppingCartID' => $transactionId,
            'TotalAmount' => $amountInCents,
            'Signature' => $signature,
            'ReturnURL' => $this->returnUrl,
            'ReturnErrorURL' => $this->errorUrl,
            'CancelURL' => $this->cancelUrl,
        ]);

        return "{$this->webUrl}/pay?{$params}";
    }

    /**
     * Generate signature for payment creation
     * Uses the FahiPay-documented SHA1/base64 signature.
     */
    public function generateSignature(string $transactionId, int $amountInCents, ?int $timestamp = null): string
    {
        $signatureData = $this->shopId.$this->secretKey.$transactionId.$this->secretKey.$amountInCents.$this->secretKey;

        return base64_encode(sha1($signatureData, true));
    }

    protected function validatePaymentInput(string $transactionId, float $amount): int
    {
        if (! $this->isConfigured()) {
            throw new FahipayException('Shop ID and Secret Key are required');
        }

        if (! preg_match('/^[A-Za-z0-9\-_]{1,100}$/', $transactionId)) {
            throw new FahipayException('Invalid transaction ID format');
        }

        if ($amount <= 0) {
            throw new FahipayException('Amount must be greater than zero');
        }

        if ($amount > 999999.99) {
            throw new FahipayException('Amount exceeds maximum allowed value');
        }

        return (int) round($amount * 100);
    }

    /**
     * Make API request
     */
    protected function makeRequest(string $endpoint, array $data, string $method = 'POST', array $headers = []): array
    {
        try {
            $request = Http::timeout($this->config['timeout'] ?? 30)
                ->withHeaders(array_merge([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ], $headers));

            $response = strtoupper($method) === 'GET'
                ? $request->get($this->baseUrl.$endpoint, $data)
                : $request->asJson()->post($this->baseUrl.$endpoint, $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('FahiPay API Error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'type' => 'error',
                'message' => $response->json('message') ?? $response->json('msg') ?? 'Request failed',
                'code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('FahiPay Request Exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'type' => 'error',
                'message' => $e->getMessage(),
                'code' => 0,
            ];
        }
    }

    /**
     * Map status from API to enum
     */
    protected function mapStatus(string $status): PaymentStatus
    {
        return match (strtolower($status)) {
            'completed', 'success', 'paid' => PaymentStatus::COMPLETED,
            'pending' => PaymentStatus::PENDING,
            'failed', 'error' => PaymentStatus::FAILED,
            'cancelled' => PaymentStatus::CANCELLED,
            default => PaymentStatus::UNKNOWN,
        };
    }

    /**
     * Get last transaction ID
     */
    public function getLastTransactionId(): ?string
    {
        return $this->lastTransactionId;
    }

    /**
     * Get last response
     */
    public function getLastResponse(): ?array
    {
        return $this->lastResponse;
    }

    /**
     * Check if payment is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->shopId) && ! empty($this->secretKey);
    }

    /**
     * Get config
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Setup routes for the package
     */
    public function routes(?string $prefix = 'fahipay'): void
    {
        require __DIR__.'/../routes/web.php';
    }
}
