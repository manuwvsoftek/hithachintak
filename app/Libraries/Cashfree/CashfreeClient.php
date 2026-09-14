<?php

declare(strict_types=1);

namespace App\Libraries\Cashfree;

use App\Models\GatewayCredentialModel;

/**
 * Thin client for the Cashfree Payment Gateway Orders API. Each Prant
 * settles to its own Cashfree merchant account (credentials stored
 * per-Prant, encrypted, in gateway_credentials — see Settings &
 * Integrations > Payment Gateways).
 *
 * @see https://docs.cashfree.com/reference/pg-new-apis-endpoint
 */
class CashfreeClient
{
    private const API_VERSION      = '2023-08-01';
    // Cashfree's Subscriptions/Plans API is versioned separately from
    // Orders — confirmed against the live OpenAPI spec for POST /plans.
    private const SUBSCRIPTIONS_API_VERSION = '2026-01-01';
    private const SANDBOX_BASE_URL = 'https://sandbox.cashfree.com/pg';
    private const LIVE_BASE_URL    = 'https://api.cashfree.com/pg';

    public function __construct(private readonly GatewayCredentialModel $credentials = new GatewayCredentialModel())
    {
    }

    public function isConfiguredForPrant(int $prantId): bool
    {
        $row = $this->credentials->forPrant($prantId);

        return $row !== null && $row['status'] === 'configured';
    }

    /**
     * Creates a Cashfree order for a Prant's merchant account and returns
     * the decoded response (includes `payment_session_id` for the Cashfree
     * Checkout JS SDK, `order_id`, `order_status`), or null on failure.
     *
     * @param array{customer_id: string, customer_phone: string, customer_name?: string, customer_email?: string} $customer
     */
    public function createOrder(int $prantId, string $orderId, float $amount, array $customer, string $returnUrl, string $notifyUrl, string $note = ''): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            log_message('error', 'Cashfree createOrder failed: no configured gateway for Prant {id}.', ['id' => $prantId]);

            return null;
        }

        $payload = [
            'order_id'       => $orderId,
            'order_amount'   => round($amount, 2),
            'order_currency' => 'INR',
            'order_note'     => $note,
            'customer_details' => [
                'customer_id'    => $customer['customer_id'],
                'customer_phone' => $customer['customer_phone'],
                'customer_name'  => $customer['customer_name'] ?? '',
                'customer_email' => $customer['customer_email'] ?? 'no-reply@example.org',
            ],
            'order_meta' => [
                'return_url' => $returnUrl,
                'notify_url' => $notifyUrl,
            ],
        ];

        return $this->request('POST', $creds, '/orders', $payload);
    }

    public function fetchOrder(int $prantId, string $orderId): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            return null;
        }

        return $this->request('GET', $creds, '/orders/' . rawurlencode($orderId));
    }

    /**
     * Creates a Cashfree Autopay subscription (recurring e-mandate) for the
     * "Autopay Monthly Donation to VHP" programme and returns the decoded
     * response (includes `subscription_session_id` for the Checkout JS
     * SDK's subscriptionsCheckout() call, `subscription_id`,
     * `subscription_status`), or null on failure. Plan details are
     * embedded inline (no separate Plan pre-creation) since each donor
     * picks their own monthly amount (≥ ₹100) — Cashfree's
     * CreateSubscriptionRequest.plan_details supports this directly.
     *
     * The `authorization_amount` here is a small token charge Cashfree
     * uses to validate the mandate (auto-refunded — see
     * `authorization_amount_refund`), not the donor's real monthly
     * amount: the first actual ₹{amount} donation, like every one after
     * it, arrives as its own SUBSCRIPTION_PAYMENT_SUCCESS webhook event
     * with payment_type=CHARGE (see PaymentController::cashfreeWebhook),
     * not at authorization time.
     *
     * Confirmed field-for-field against Cashfree's live OpenAPI spec for
     * POST /subscriptions and their Subscriptions webhooks reference
     * (both 2026-01-01) — endpoint, payload shape, and the webhook event
     * types/paths cashfreeWebhook() reacts to. Still not verified against
     * an actual sandbox delivery (no network access to cashfree.com from
     * this build environment), so a live end-to-end test before go-live
     * is still worthwhile, but this is no longer a guess.
     *
     * @param array{customer_phone: string, customer_name?: string, customer_email?: ?string} $customer
     */
    public function createSubscription(int $prantId, string $subscriptionId, float $monthlyAmount, array $customer, string $returnUrl, string $note = ''): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            log_message('error', 'Cashfree createSubscription failed: no configured gateway for Prant {id}.', ['id' => $prantId]);

            return null;
        }

        $payload = [
            'subscription_id' => $subscriptionId,
            'customer_details' => [
                'customer_name'  => $customer['customer_name'] ?? '',
                'customer_phone' => $customer['customer_phone'],
                'customer_email' => $customer['customer_email'] ?: 'no-reply@example.org',
            ],
            'plan_details' => [
                'plan_name'          => 'VHP Autopay Monthly Donation',
                'plan_type'          => 'PERIODIC',
                'plan_currency'      => 'INR',
                'plan_amount'        => round($monthlyAmount, 2),
                'plan_max_amount'    => round($monthlyAmount, 2),
                'plan_max_cycles'    => 0,
                'plan_intervals'     => 1,
                'plan_interval_type' => 'MONTH',
                'plan_note'          => $note,
            ],
            'authorization_details' => [
                'authorization_amount'        => 1,
                'authorization_amount_refund' => true,
                'payment_methods'             => ['upi', 'enach'],
            ],
            'subscription_meta' => [
                'return_url'            => $returnUrl,
                'notification_channel'  => ['SMS'],
            ],
        ];

        return $this->request('POST', $creds, '/subscriptions', $payload, self::SUBSCRIPTIONS_API_VERSION);
    }

    public function fetchSubscription(int $prantId, string $subscriptionId): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            return null;
        }

        return $this->request('GET', $creds, '/subscriptions/' . rawurlencode($subscriptionId), null, self::SUBSCRIPTIONS_API_VERSION);
    }

    /**
     * Verifies a Cashfree webhook payload signature.
     * signature = base64(HMAC-SHA256(secretKey, timestamp . rawBody))
     *
     * @see https://docs.cashfree.com/reference/pg-webhooks-verify-signature
     */
    public function verifyWebhookSignature(int $prantId, string $rawBody, string $timestamp, string $signature): bool
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds || empty($creds['secret_key'])) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . $rawBody, $creds['secret_key'], true));

        return hash_equals($expected, $signature);
    }

    private function credentialsFor(int $prantId): ?array
    {
        $row = $this->credentials->forPrant($prantId);
        if (! $row || $row['status'] !== 'configured') {
            return null;
        }

        return $this->credentials->decrypted($row);
    }

    private function baseUrl(array $creds): string
    {
        return $creds['environment'] === 'production' ? self::LIVE_BASE_URL : self::SANDBOX_BASE_URL;
    }

    private function request(string $method, array $creds, string $path, ?array $body = null, ?string $apiVersion = null): ?array
    {
        try {
            $client = service('curlrequest', ['timeout' => 15]);

            $options = [
                'headers' => [
                    'x-client-id'     => $creds['app_id'],
                    'x-client-secret' => $creds['secret_key'],
                    'x-api-version'   => $apiVersion ?? self::API_VERSION,
                    'content-type'    => 'application/json',
                ],
                'http_errors' => false,
            ];
            if ($body !== null) {
                $options['json'] = $body;
            }

            $response = $client->request($method, $this->baseUrl($creds) . $path, $options);
            $decoded  = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return $decoded ?? [];
            }

            log_message('error', 'Cashfree API {method} {path} failed: HTTP {code} — {body}', [
                'method' => $method,
                'path'   => $path,
                'code'   => $response->getStatusCode(),
                'body'   => $response->getBody(),
            ]);

            return null;
        } catch (\Throwable $e) {
            log_message('error', 'Cashfree API exception: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }
}
