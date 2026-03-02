<?php

declare(strict_types=1);

namespace Plugs\TransactionHandler;

/*
| -----------------------------------------------------------------------
| PaymentTransactionHandler
| -----------------------------------------------------------------------
*/

use Exception;
use Plugs\Facades\Payment;
use Psr\Log\LoggerInterface;

class PaymentTransactionHandler
{
    private string $platform;
    private array $config;
    /** @var \Plugs\Payment\Contracts\PaymentDriverInterface */
    private $adapter;
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
     * @param string $platform The payment platform name
     * @param array $config Platform configuration
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $platform, array $config, LoggerInterface $logger)
    {
        $this->platform = strtolower($platform);
        $this->config = $config;
        $this->logger = $logger;
        $this->initializeAdapter();
    }

    /**
     * Initialize the appropriate payment adapter
     */
    private function initializeAdapter(): void
    {
        try {
            $this->adapter = Payment::driver($this->platform);
        } catch (Exception $e) {
            throw new Exception("Unsupported platform or configuration error: {$e->getMessage()}");
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
            $response = $this->adapter->initialize($payload);

            $resultData = [
                'reference' => $response->reference,
                'authorization_url' => $response->authorization_url,
                'status' => $response->status,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'message' => $response->message,
                'metadata' => $response->metadata
            ];

            $transactionResult = TransactionResult::success($resultData, self::TYPE_ONE_TIME, $this->platform);

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
        throw new Exception('Subscriptions are currently being migrated to the Elite Unified Subscription Layer. Use $subscriptionFacade->create() instead.');
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId Subscription ID
     * @return TransactionResult
     */
    public function cancel(string $subscriptionId): TransactionResult
    {
        throw new Exception('Subscriptions are currently being migrated to the Elite Unified Subscription Layer.');
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
            /** @var \Plugs\Payout\DTO\TransferResponse $response */
            $response = \Plugs\Facades\Payout::driver($this->platform)->transfer($payload);

            $resultData = [
                'reference' => $response->reference,
                'status' => $response->status,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'message' => $response->message,
                'metadata' => $response->metadata
            ];

            return TransactionResult::success($resultData, self::TYPE_TRANSFER, $this->platform);
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
            /** @var \Plugs\Payout\DTO\WithdrawResponse $response */
            $response = \Plugs\Facades\Payout::driver($this->platform)->withdraw($payload);

            $resultData = [
                'reference' => $response->reference,
                'status' => $response->status,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'message' => $response->message,
                'metadata' => $response->metadata
            ];

            return TransactionResult::success($resultData, self::TYPE_WITHDRAWAL, $this->platform);
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
            // New Elite Interface expects (string $reference, float $amount)
            $response = $this->adapter->refund(
                $data['transaction_id'],
                (float) ($data['amount'] ?? $this->amount)
            );

            $resultData = [
                'reference' => $response->reference,
                'status' => $response->status,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'message' => $response->message,
                'metadata' => $response->metadata
            ];

            return TransactionResult::success($resultData, self::TYPE_REFUND, $this->platform);
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
            $response = $this->adapter->verify($reference);

            $resultData = [
                'reference' => $response->reference,
                'status' => $response->status,
                'amount' => $response->amount,
                'currency' => $response->currency,
                'message' => $response->message,
                'metadata' => $response->metadata
            ];

            return TransactionResult::success($resultData, 'verify', $this->platform);
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
        throw new \Exception('Native GET is deprecated via TransactionHandler. Check your database ledger first.');
    }

    /**
     * List all transactions
     *
     * @param array $filters Optional filters
     * @return array List of transactions
     */
    public function list(array $filters = []): array
    {
        throw new \Exception('Native List is deprecated. Use your unified transaction ledger.');
    }

    /**
     * Get balance from payment platform
     *
     * @return array Balance information
     */
    public function getBalance(): array
    {
        return \Plugs\Facades\Payout::driver($this->platform)->getBalance();
    }

    /**
     * Create a payment recipient
     *
     * @param array $data Recipient data
     * @return array Recipient details
     */
    public function createRecipient(array $data): array
    {
        return \Plugs\Facades\Payout::driver($this->platform)->createRecipient($data);
    }

    /**
     * Webhook handler
     *
     * @param array $payload Webhook payload (Legacy payload signature)
     * @return TransactionResult
     */
    public function handleWebhook(array $payload): TransactionResult
    {
        throw new \Exception('Calling handleWebhook via the legacy TransactionHandler is deprecated. Please point your webhooks directly to the new Plugs\Payment\WebhookRouter.');
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
