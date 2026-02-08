<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler;

/*
| -----------------------------------------------------------------------
| PaymentTransactionHandler
| -----------------------------------------------------------------------
*/

use Exception;
use Plugs\TransactionHandler\Adapter\BTCPayAdapter;
use Plugs\TransactionHandler\Adapter\FlutterwaveAdapter;
use Plugs\TransactionHandler\Adapter\PayoneerAdapter;
use Plugs\TransactionHandler\Adapter\PayPalAdapter;
use Plugs\TransactionHandler\Adapter\PaystackAdapter;
use Plugs\TransactionHandler\Adapter\StripeAdapter;
use Psr\Log\LoggerInterface;

class PaymentTransactionHandler
{
    private string $platform;
    private array $config;
    private PaymentAdapterInterface $adapter;
    private ?LoggerInterface $logger;

    // Fluent context
    private float $amount = 0.0;
    private string $currency = 'NGN';
    private string $email = '';
    private ?string $reference = null;
    private array $metadata = [];
    private ?string $callbackUrl = null;
    private ?string $description = null;
    private array $extraData = [];

    // Transaction types
    public const TYPE_ONE_TIME = 'one_time';
    public const TYPE_SUBSCRIPTION = 'subscription';
    public const TYPE_TRANSFER = 'transfer';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_REFUND = 'refund';

    // Transaction statuses
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    // Supported platforms
    public const PLATFORM_PAYSTACK = 'paystack';
    public const PLATFORM_STRIPE = 'stripe';
    public const PLATFORM_PAYPAL = 'paypal';
    public const PLATFORM_PAYONEER = 'payoneer';
    public const PLATFORM_FLUTTERWAVE = 'flutterwave';
    public const PLATFORM_BTCPAY = 'btcpay';

    /**
     * Constructor
     *
     * @param string $platform Payment platform name
     * @param array $config Platform configuration
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $platform, array $config, ?LoggerInterface $logger = null)
    {
        $this->platform = strtolower($platform);
        $this->config = $config;
        $this->logger = $logger ?? (function_exists('app') ? app('log') : null);
        $this->initializeAdapter();
    }

    /**
     * Initialize the appropriate payment adapter
     */
    private function initializeAdapter(): void
    {
        switch ($this->platform) {
            case self::PLATFORM_PAYSTACK:
                $this->adapter = new PaystackAdapter($this->config);

                break;
            case self::PLATFORM_STRIPE:
                $this->adapter = new StripeAdapter($this->config);

                break;
            case self::PLATFORM_PAYPAL:
                $this->adapter = new PayPalAdapter();

                break;
            case self::PLATFORM_FLUTTERWAVE:
                $this->adapter = new FlutterwaveAdapter($this->config);

                break;
            case self::PLATFORM_PAYONEER:
                $this->adapter = new PayoneerAdapter();

                break;
            case self::PLATFORM_BTCPAY:
                $this->adapter = new BTCPayAdapter($this->config);

                break;
            default:
                throw new Exception("Unsupported platform: {$this->platform}");
        }
    }

    // --- Fluent Interface building methods ---

    public function amount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = strtoupper($currency);

        return $this;
    }

    public function email(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function reference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function metadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function callback(string $url): self
    {
        $this->callbackUrl = $url;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function with(array $data): self
    {
        $this->extraData = array_merge($this->extraData, $data);

        return $this;
    }

    // --- Processing methods ---

    /**
     * Process a one-time payment using fluent context or provided data
     *
     * @param array $data Optional data override
     * @return TransactionResult
     */
    public function pay(array $data = []): TransactionResult
    {
        $payload = array_merge([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'email' => $this->email,
            'reference' => $this->reference ?? $this->generateReference(),
            'metadata' => $this->metadata,
            'callback_url' => $this->callbackUrl,
            'description' => $this->description,
        ], $this->extraData, $data);

        $this->validatePaymentData($payload);

        $this->logInfo("Initiating payment of {$payload['amount']} {$payload['currency']} for {$payload['email']}", [
            'reference' => $payload['reference'],
            'platform' => $this->platform,
        ]);

        try {
            $result = $this->adapter->charge($payload);

            $transactionResult = TransactionResult::success($result, self::TYPE_ONE_TIME, $this->platform);

            $this->logInfo("Payment initiated successfully", ['reference' => $transactionResult->getReference()]);

            return $transactionResult;
        } catch (Exception $e) {
            $this->logError("Payment failed: " . $e->getMessage(), ['payload' => $payload]);

            return TransactionResult::failed($e->getMessage(), self::TYPE_ONE_TIME, $this->platform);
        }
    }

    /**
     * Create a subscription
     *
     * @param array $data Subscription data
     * @return TransactionResult
     */
    public function subscribe(array $data = []): TransactionResult
    {
        $payload = array_merge([
            'email' => $this->email,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
        ], $this->extraData, $data);

        $this->validateSubscriptionData($payload);

        try {
            $result = $this->adapter->createSubscription($payload);

            return TransactionResult::success($result, self::TYPE_SUBSCRIPTION, $this->platform);
        } catch (Exception $e) {
            $this->logError("Subscription failed: " . $e->getMessage(), ['payload' => $payload]);

            return TransactionResult::failed($e->getMessage(), self::TYPE_SUBSCRIPTION, $this->platform);
        }
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId Subscription ID
     * @return TransactionResult
     */
    public function cancel(string $subscriptionId): TransactionResult
    {
        try {
            $result = $this->adapter->cancelSubscription($subscriptionId);

            return TransactionResult::success($result, self::TYPE_SUBSCRIPTION, $this->platform);
        } catch (Exception $e) {
            return TransactionResult::failed($e->getMessage(), self::TYPE_SUBSCRIPTION, $this->platform);
        }
    }

    /**
     * Transfer funds to another account
     *
     * @param array $data Transfer data
     * @return TransactionResult
     */
    public function transfer(array $data = []): TransactionResult
    {
        $payload = array_merge([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference ?? $this->generateReference(),
            'metadata' => $this->metadata,
        ], $this->extraData, $data);

        $this->validateTransferData($payload);

        try {
            $result = $this->adapter->transfer($payload);

            return TransactionResult::success($result, self::TYPE_TRANSFER, $this->platform);
        } catch (Exception $e) {
            return TransactionResult::failed($e->getMessage(), self::TYPE_TRANSFER, $this->platform);
        }
    }

    /**
     * Withdraw funds to bank account
     *
     * @param array $data Withdrawal data
     * @return TransactionResult
     */
    public function withdraw(array $data = []): TransactionResult
    {
        $payload = array_merge([
            'amount' => $this->amount,
            'currency' => $this->currency,
            'reference' => $this->reference ?? $this->generateReference(),
        ], $this->extraData, $data);

        $this->validateWithdrawalData($payload);

        try {
            $result = $this->adapter->withdraw($payload);

            return TransactionResult::success($result, self::TYPE_WITHDRAWAL, $this->platform);
        } catch (Exception $e) {
            return TransactionResult::failed($e->getMessage(), self::TYPE_WITHDRAWAL, $this->platform);
        }
    }

    /**
     * Process a refund
     *
     * @param array $data Refund data
     * @return TransactionResult
     */
    public function refund(array $data): TransactionResult
    {
        $this->validateRefundData($data);

        try {
            $result = $this->adapter->refund($data);

            return TransactionResult::success($result, self::TYPE_REFUND, $this->platform);
        } catch (Exception $e) {
            return TransactionResult::failed($e->getMessage(), self::TYPE_REFUND, $this->platform);
        }
    }

    /**
     * Verify a transaction
     *
     * @param string $reference Transaction reference
     * @return TransactionResult
     */
    public function verify(string $reference): TransactionResult
    {
        try {
            $result = $this->adapter->verify($reference);

            return TransactionResult::success($result, 'verify', $this->platform);
        } catch (Exception $e) {
            return TransactionResult::failed($e->getMessage(), 'verify', $this->platform);
        }
    }

    /**
     * Get transaction details
     *
     * @param string $transactionId Transaction ID
     * @return array Transaction details
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->adapter->getTransaction($transactionId);
    }

    /**
     * List all transactions
     *
     * @param array $filters Optional filters
     * @return array List of transactions
     */
    public function list(array $filters = []): array
    {
        return $this->adapter->listTransactions($filters);
    }

    /**
     * Get balance from payment platform
     *
     * @return array Balance information
     */
    public function getBalance(): array
    {
        return $this->adapter->getBalance();
    }

    /**
     * Create a payment recipient
     *
     * @param array $data Recipient data
     * @return array Recipient details
     */
    public function createRecipient(array $data): array
    {
        return $this->adapter->createRecipient($data);
    }

    /**
     * Webhook handler
     *
     * @param array $payload Webhook payload
     * @return TransactionResult
     */
    public function handleWebhook(array $payload): TransactionResult
    {
        try {
            if (!$this->adapter->verifyWebhookSignature($payload)) {
                throw new Exception('Invalid webhook signature');
            }

            $result = $this->adapter->processWebhook($payload);
            $this->logInfo("Webhook processed", ['event' => $result['event'] ?? 'unknown']);

            return TransactionResult::success($result, 'webhook', $this->platform);
        } catch (Exception $e) {
            $this->logError("Webhook processing failed: " . $e->getMessage());

            return TransactionResult::failed($e->getMessage(), 'webhook', $this->platform);
        }
    }

    /**
     * Generate unique reference
     *
     * @return string
     */
    private function generateReference(): string
    {
        return strtoupper(uniqid('TXN_' . time() . '_'));
    }

    /**
     * Helper for logging info
     */
    private function logInfo(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->info("[Payment] " . $message, $context);
        }
    }

    /**
     * Helper for logging errors
     */
    private function logError(string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->error("[Payment] " . $message, $context);
        }
    }

    /**
     * Validation methods
     */
    private function validatePaymentData(array $data): void
    {
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            throw new Exception('Valid amount is required');
        }
        if (empty($data['email'])) {
            throw new Exception('Email is required');
        }
    }

    private function validateSubscriptionData(array $data): void
    {
        if (empty($data['plan_code']) && empty($data['price_id'])) {
            throw new Exception('Plan code or Price ID is required');
        }
        if (empty($data['email'])) {
            throw new Exception('Email is required');
        }
    }

    private function validateTransferData(array $data): void
    {
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            throw new Exception('Valid amount is required');
        }
        if (empty($data['recipient']) && empty($data['account_number'])) {
            throw new Exception('Recipient or Account details required');
        }
    }

    private function validateWithdrawalData(array $data): void
    {
        if (empty($data['amount']) || !is_numeric($data['amount'])) {
            throw new Exception('Valid amount is required');
        }
        if (empty($data['account_number'])) {
            throw new Exception('Account number is required');
        }
    }

    private function validateRefundData(array $data): void
    {
        if (empty($data['transaction_id'])) {
            throw new Exception('Transaction ID is required');
        }
    }

    /**
     * Compatibility methods for legacy code
     */
    public function processOneTimePayment(array $data): array
    {
        return $this->pay($data)->toArray();
    }

    public function createSubscription(array $data): array
    {
        return $this->subscribe($data)->toArray();
    }

    public function transferFunds(array $data): array
    {
        return $this->transfer($data)->toArray();
    }

    public function withdrawFunds(array $data): array
    {
        return $this->withdraw($data)->toArray();
    }

    public function refundTransaction(array $data): array
    {
        return $this->refund($data)->toArray();
    }

    public function verifyTransaction(string $reference): array
    {
        return $this->verify($reference)->toArray();
    }

    public function getTransactionDetails(string $transactionId): array
    {
        return $this->getTransaction($transactionId);
    }

    public function listTransactions(array $filters = []): array
    {
        return $this->list($filters);
    }
}
