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

class PaymentTransactionHandler
{
    private $platform;
    private $config;
    private $adapter;

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
     */
    public function __construct(string $platform, array $config)
    {
        $this->platform = strtolower($platform);
        $this->config = $config;
        $this->initializeAdapter();
    }

    /**
     * Initialize the appropriate payment adapter
     */
    private function initializeAdapter()
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

    /**
     * Process a one-time payment
     *
     * @param array $data Payment data
     * @return array Transaction result
     */
    public function processOneTimePayment(array $data): array
    {
        $this->validatePaymentData($data);

        try {
            $result = $this->adapter->charge([
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'email' => $data['email'],
                'reference' => $data['reference'] ?? $this->generateReference(),
                'metadata' => $data['metadata'] ?? [],
                'callback_url' => $data['callback_url'] ?? null,
            ]);

            return $this->formatResponse($result, self::TYPE_ONE_TIME);
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_ONE_TIME);
        }
    }

    /**
     * Create a subscription
     *
     * @param array $data Subscription data
     * @return array Transaction result
     */
    public function createSubscription(array $data): array
    {
        $this->validateSubscriptionData($data);

        try {
            $result = $this->adapter->createSubscription([
                'plan_code' => $data['plan_code'],
                'customer_email' => $data['email'],
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'interval' => $data['interval'] ?? 'monthly',
                'start_date' => $data['start_date'] ?? date('Y-m-d'),
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $this->formatResponse($result, self::TYPE_SUBSCRIPTION);
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_SUBSCRIPTION);
        }
    }

    /**
     * Cancel a subscription
     *
     * @param string $subscriptionId Subscription ID
     * @return array Transaction result
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        try {
            $result = $this->adapter->cancelSubscription($subscriptionId);

            return [
                'status' => self::STATUS_SUCCESS,
                'message' => 'Subscription cancelled successfully',
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_SUBSCRIPTION);
        }
    }

    /**
     * Transfer funds to another account
     *
     * @param array $data Transfer data
     * @return array Transaction result
     */
    public function transferFunds(array $data): array
    {
        $this->validateTransferData($data);

        try {
            $result = $this->adapter->transfer([
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'recipient' => $data['recipient'],
                'reason' => $data['reason'] ?? 'Fund Transfer',
                'reference' => $data['reference'] ?? $this->generateReference(),
                'metadata' => $data['metadata'] ?? [],
            ]);

            return $this->formatResponse($result, self::TYPE_TRANSFER);
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_TRANSFER);
        }
    }

    /**
     * Withdraw funds to bank account
     *
     * @param array $data Withdrawal data
     * @return array Transaction result
     */
    public function withdrawFunds(array $data): array
    {
        $this->validateWithdrawalData($data);

        try {
            $result = $this->adapter->withdraw([
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'USD',
                'bank_code' => $data['bank_code'],
                'account_number' => $data['account_number'],
                'account_name' => $data['account_name'],
                'reference' => $data['reference'] ?? $this->generateReference(),
                'narration' => $data['narration'] ?? 'Withdrawal',
            ]);

            return $this->formatResponse($result, self::TYPE_WITHDRAWAL);
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_WITHDRAWAL);
        }
    }

    /**
     * Process a refund
     *
     * @param array $data Refund data
     * @return array Transaction result
     */
    public function refundTransaction(array $data): array
    {
        $this->validateRefundData($data);

        try {
            $result = $this->adapter->refund([
                'transaction_id' => $data['transaction_id'],
                'amount' => $data['amount'] ?? null,
                'reason' => $data['reason'] ?? 'Customer request',
            ]);

            return $this->formatResponse($result, self::TYPE_REFUND);
        } catch (Exception $e) {
            return $this->handleError($e, self::TYPE_REFUND);
        }
    }

    /**
     * Verify a transaction
     *
     * @param string $reference Transaction reference
     * @return array Verification result
     */
    public function verifyTransaction(string $reference): array
    {
        try {
            $result = $this->adapter->verify($reference);

            return [
                'status' => self::STATUS_SUCCESS,
                'verified' => true,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return [
                'status' => self::STATUS_FAILED,
                'verified' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get transaction details
     *
     * @param string $transactionId Transaction ID
     * @return array Transaction details
     */
    public function getTransactionDetails(string $transactionId): array
    {
        try {
            $result = $this->adapter->getTransaction($transactionId);

            return [
                'status' => self::STATUS_SUCCESS,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, 'fetch');
        }
    }

    /**
     * List all transactions
     *
     * @param array $filters Optional filters
     * @return array List of transactions
     */
    public function listTransactions(array $filters = []): array
    {
        try {
            $result = $this->adapter->listTransactions($filters);

            return [
                'status' => self::STATUS_SUCCESS,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, 'list');
        }
    }

    /**
     * Get balance from payment platform
     *
     * @return array Balance information
     */
    public function getBalance(): array
    {
        try {
            $result = $this->adapter->getBalance();

            return [
                'status' => self::STATUS_SUCCESS,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, 'balance');
        }
    }

    /**
     * Create a payment recipient
     *
     * @param array $data Recipient data
     * @return array Recipient details
     */
    public function createRecipient(array $data): array
    {
        try {
            $result = $this->adapter->createRecipient([
                'type' => $data['type'] ?? 'nuban',
                'name' => $data['name'],
                'account_number' => $data['account_number'],
                'bank_code' => $data['bank_code'],
                'currency' => $data['currency'] ?? 'USD',
                'metadata' => $data['metadata'] ?? [],
            ]);

            return [
                'status' => self::STATUS_SUCCESS,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, 'recipient');
        }
    }

    /**
     * Webhook handler
     *
     * @param array $payload Webhook payload
     * @return array Processing result
     */
    public function handleWebhook(array $payload): array
    {
        try {
            // Verify webhook signature
            if (!$this->adapter->verifyWebhookSignature($payload)) {
                throw new Exception('Invalid webhook signature');
            }

            $result = $this->adapter->processWebhook($payload);

            return [
                'status' => self::STATUS_SUCCESS,
                'data' => $result,
            ];
        } catch (Exception $e) {
            return $this->handleError($e, 'webhook');
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
     * Format response
     *
     * @param mixed $result
     * @param string $type
     * @return array
     */
    private function formatResponse($result, string $type): array
    {
        return [
            'status' => self::STATUS_SUCCESS,
            'type' => $type,
            'platform' => $this->platform,
            'data' => $result,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Handle errors
     *
     * @param Exception $e
     * @param string $type
     * @return array
     */
    private function handleError(Exception $e, string $type): array
    {
        return [
            'status' => self::STATUS_FAILED,
            'type' => $type,
            'platform' => $this->platform,
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s'),
        ];
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
        if (empty($data['plan_code'])) {
            throw new Exception('Plan code is required');
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
        if (empty($data['recipient'])) {
            throw new Exception('Recipient is required');
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
        if (empty($data['bank_code'])) {
            throw new Exception('Bank code is required');
        }
    }

    private function validateRefundData(array $data): void
    {
        if (empty($data['transaction_id'])) {
            throw new Exception('Transaction ID is required');
        }
    }
}
