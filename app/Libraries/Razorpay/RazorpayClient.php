<?php

declare(strict_types=1);

namespace App\Libraries\Razorpay;

use App\Models\GatewayCredentialModel;

/**
 * Thin client for the Razorpay Orders/Subscriptions API — the second
 * payment-gateway provider alongside Cashfree (see CashfreeClient, which
 * this mirrors method-for-method so EnrolmentService can treat both
 * interchangeably). Each Prant settles to its own Razorpay account,
 * credentials stored per-Prant-per-provider, encrypted, in
 * gateway_credentials — see Settings & Integrations > Payment Gateways.
 *
 * Two structural differences from Cashfree worth flagging:
 *  - Auth is HTTP Basic (key_id:key_secret), not custom headers.
 *  - Razorpay has no "inline plan" for a subscription — a Plan object
 *    has to exist first (one per distinct monthly amount) and the
 *    subscription is created against its id. createSubscription() here
 *    creates that Plan on the fly before creating the subscription, so
 *    callers still only ever deal with one method call, same as Cashfree.
 *  - Amounts are in paise (smallest currency unit), not rupees.
 *
 * @see https://razorpay.com/docs/api/orders/
 * @see https://razorpay.com/docs/api/payments/subscriptions/
 */
class RazorpayClient
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    /**
     * Razorpay subscriptions require a finite total_count — there's no
     * "unlimited" option the way Cashfree's plan_max_cycles=0 is. 600
     * monthly cycles (50 years) is used as a practical stand-in for
     * "runs until the donor or Trust cancels it".
     */
    private const SUBSCRIPTION_TOTAL_COUNT = 600;

    public function __construct(private readonly GatewayCredentialModel $credentials = new GatewayCredentialModel())
    {
    }

    public function isConfiguredForPrant(int $prantId): bool
    {
        $row = $this->credentials->forPrant($prantId, 'razorpay');

        return $row !== null && $row['status'] === 'configured';
    }

    /**
     * Razorpay's key_id (unlike Cashfree's app_id) has to be handed to
     * the browser — Checkout JS needs it to open, it's the "publishable"
     * half of the credential pair, not a secret. Null if not configured.
     */
    public function keyIdFor(int $prantId): ?string
    {
        return $this->credentialsFor($prantId)['app_id'] ?? null;
    }

    /**
     * Creates a Razorpay order for a Prant's account and returns the
     * decoded response (`id` is Razorpay's own order id — what's stored
     * as razorpay_order_id — plus `amount`, `status`), or null on
     * failure. $receipt is our own internal reference (e.g.
     * "HTC-123-abc" or "REMIT-123-abc"); Razorpay stores it verbatim but
     * doesn't use it as the order id the way Cashfree does with the id we
     * pass it, so routing a webhook back to the right flow is done by the
     * enrolment's own status rather than this string — see
     * PaymentController::razorpayWebhook().
     */
    public function createOrder(int $prantId, string $receipt, float $amount, string $note = ''): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            log_message('error', 'Razorpay createOrder failed: no configured gateway for Prant {id}.', ['id' => $prantId]);

            return null;
        }

        $payload = [
            'amount'   => $this->toPaise($amount),
            'currency' => 'INR',
            'receipt'  => $receipt,
            'notes'    => ['note' => $note],
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

    /** The payment(s) captured against an order — used to pull the payment id once `fetchOrder()` shows status=paid. */
    public function fetchOrderPayments(int $prantId, string $orderId): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            return null;
        }

        return $this->request('GET', $creds, '/orders/' . rawurlencode($orderId) . '/payments');
    }

    /**
     * Creates a Razorpay Plan for this exact monthly amount, then a
     * Subscription against it, and returns the subscription response
     * (`id` for the Checkout JS SDK's subscription_id option, `status`),
     * or null on failure. A new Plan per distinct amount is unavoidable —
     * Razorpay Plans are reusable fixed-amount objects, there's no
     * per-subscription custom-amount field — but harmless to create one
     * each time since nothing else ever reuses it.
     */
    public function createSubscription(int $prantId, float $monthlyAmount, array $customer, string $note = ''): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            log_message('error', 'Razorpay createSubscription failed: no configured gateway for Prant {id}.', ['id' => $prantId]);

            return null;
        }

        $plan = $this->request('POST', $creds, '/plans', [
            'period'   => 'monthly',
            'interval' => 1,
            'item'     => [
                'name'     => 'VHP Autopay Monthly Donation',
                'amount'   => $this->toPaise($monthlyAmount),
                'currency' => 'INR',
            ],
            'notes' => ['note' => $note],
        ]);

        if (! $plan || empty($plan['id'])) {
            return null;
        }

        $subscription = $this->request('POST', $creds, '/subscriptions', [
            'plan_id'         => $plan['id'],
            'total_count'     => self::SUBSCRIPTION_TOTAL_COUNT,
            'customer_notify' => 0,
            'notes'           => [
                'note'            => $note,
                'customer_phone'  => $customer['customer_phone'] ?? '',
                'customer_name'   => $customer['customer_name'] ?? '',
            ],
        ]);

        if (! $subscription) {
            return null;
        }

        $subscription['plan_id'] = $plan['id'];

        return $subscription;
    }

    public function fetchSubscription(int $prantId, string $subscriptionId): ?array
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds) {
            return null;
        }

        return $this->request('GET', $creds, '/subscriptions/' . rawurlencode($subscriptionId));
    }

    /**
     * Verifies a Razorpay webhook payload signature.
     * signature = hex(HMAC-SHA256(secretKey, rawBody))
     *
     * @see https://razorpay.com/docs/webhooks/validate-test/
     */
    public function verifyWebhookSignature(int $prantId, string $rawBody, string $signature): bool
    {
        $creds = $this->credentialsFor($prantId);
        if (! $creds || empty($creds['secret_key'])) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $creds['secret_key']);

        return hash_equals($expected, $signature);
    }

    private function toPaise(float $amount): int
    {
        return (int) round($amount * 100);
    }

    private function credentialsFor(int $prantId): ?array
    {
        $row = $this->credentials->forPrant($prantId, 'razorpay');
        if (! $row || $row['status'] !== 'configured') {
            return null;
        }

        return $this->credentials->decrypted($row);
    }

    private function request(string $method, array $creds, string $path, ?array $body = null): ?array
    {
        try {
            $client = service('curlrequest', ['timeout' => 15]);

            $options = [
                'auth'        => [$creds['app_id'], $creds['secret_key']],
                'headers'     => ['content-type' => 'application/json'],
                'http_errors' => false,
            ];
            if ($body !== null) {
                $options['json'] = $body;
            }

            $response = $client->request($method, self::BASE_URL . $path, $options);
            $decoded  = json_decode($response->getBody(), true);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return $decoded ?? [];
            }

            log_message('error', 'Razorpay API {method} {path} failed: HTTP {code} — {body}', [
                'method' => $method,
                'path'   => $path,
                'code'   => $response->getStatusCode(),
                'body'   => $response->getBody(),
            ]);

            return null;
        } catch (\Throwable $e) {
            log_message('error', 'Razorpay API exception: {msg}', ['msg' => $e->getMessage()]);

            return null;
        }
    }
}
