<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Cashfree\CashfreeClient;
use App\Libraries\Enrolment\EnrolmentService;

/** Receives Cashfree payment-gateway webhooks — one endpoint per Prant. */
class PaymentController extends BaseController
{
    public function cashfreeWebhook($prantId)
    {
        $prantId = (int) $prantId;

        $rawBody   = $this->request->getBody();
        $signature = $this->request->getHeaderLine('x-webhook-signature');
        $timestamp = $this->request->getHeaderLine('x-webhook-timestamp');

        if (! $signature || ! $timestamp
            || ! (new CashfreeClient())->verifyWebhookSignature($prantId, $rawBody, $timestamp, $signature)
        ) {
            log_message('warning', 'Cashfree webhook rejected: bad or missing signature for Prant {id}.', ['id' => $prantId]);

            return $this->response->setStatusCode(400)->setBody('invalid signature');
        }

        $payload = json_decode($rawBody, true) ?? [];
        $type    = $payload['type'] ?? '';
        $service = new EnrolmentService();

        if ($type === 'PAYMENT_SUCCESS_WEBHOOK') {
            $orderId   = $payload['data']['order']['order_id'] ?? null;
            $paymentId = $payload['data']['payment']['cf_payment_id'] ?? null;

            if ($orderId && $paymentId) {
                // REMIT- orders are a Karyakarta paying collected Hithachintak
                // cash onward to the Trust (createRemittanceOrder()), not a
                // donor paying for their own enrolment — routed separately so
                // it lands on confirmCashRemittance() instead.
                if (str_starts_with($orderId, 'REMIT-')) {
                    $service->confirmCashRemittance($orderId, (string) $paymentId);
                } else {
                    $service->markCashfreePaid($orderId, (string) $paymentId);
                }
            }
        }

        // Autopay/Subscriptions events — confirmed against Cashfree's
        // Subscriptions webhooks reference (2026-01-01 version: snake_case
        // fields, e.g. authorization_status not authorizationStatus —
        // that assumes the Prant's webhook URL is registered under that
        // API version in the Cashfree dashboard, same version this app
        // uses for its own subscription-create calls).
        //
        // SUBSCRIPTION_AUTH_STATUS fires once for the checkout/mandate
        // step itself (success or failure) — this is a small refunded
        // token charge, not the donor's real amount, so it only flips the
        // mandate to active, never a receipt (see
        // EnrolmentService::activateAutopaySubscription()).
        if ($type === 'SUBSCRIPTION_AUTH_STATUS') {
            $subscriptionId = $payload['data']['subscription_id'] ?? null;
            $success        = ($payload['data']['payment_status'] ?? '') === 'SUCCESS';

            if ($subscriptionId && $success) {
                $service->activateAutopaySubscription($subscriptionId);
            }
        }

        // SUBSCRIPTION_PAYMENT_SUCCESS/FAILED cover every payment attempt
        // on the subscription, the auth one included (payment_type=AUTH)
        // — only payment_type=CHARGE is a real recurring donation charge;
        // the AUTH one is handled above via SUBSCRIPTION_AUTH_STATUS, so
        // it's skipped here to avoid double-processing the same payment.
        if ($type === 'SUBSCRIPTION_PAYMENT_SUCCESS' || $type === 'SUBSCRIPTION_PAYMENT_FAILED') {
            $subscriptionId = $payload['data']['subscription_id'] ?? null;
            $paymentId      = $payload['data']['payment_id'] ?? $payload['data']['cf_payment_id'] ?? null;
            $amount         = $payload['data']['payment_amount'] ?? null;
            $isCharge       = ($payload['data']['payment_type'] ?? '') === 'CHARGE';

            if ($subscriptionId && $paymentId && $isCharge) {
                $success = $type === 'SUBSCRIPTION_PAYMENT_SUCCESS';
                $model   = new \App\Models\AutopaySubscriptionModel();
                $sub     = $model->forCashfreeSubscriptionId($subscriptionId);

                if ($sub) {
                    $service->recordAutopayCharge($subscriptionId, (string) $paymentId, (float) ($amount ?? $sub->amount), $success);
                }
            }
        }

        // General mandate lifecycle changes (paused/cancelled/expired by
        // the donor's bank or by Cashfree). An ACTIVE transition here is
        // routed through activateAutopaySubscription() too — harmless if
        // SUBSCRIPTION_AUTH_STATUS already did it (that call is
        // idempotent), a useful fallback if it didn't.
        if ($type === 'SUBSCRIPTION_STATUS_CHANGED') {
            $subscriptionId = $payload['data']['subscription_details']['subscription_id'] ?? null;
            $status         = $payload['data']['subscription_details']['subscription_status'] ?? '';

            if ($subscriptionId) {
                if ($status === 'ACTIVE') {
                    $service->activateAutopaySubscription($subscriptionId);
                } else {
                    $mapped = match ($status) {
                        'ON_HOLD', 'CUSTOMER_PAUSED' => 'paused',
                        'BANK_APPROVAL_PENDING'       => 'pending',
                        'COMPLETED', 'CUSTOMER_CANCELLED', 'EXPIRED', 'LINK_EXPIRED', 'CANCELLED', 'CARD_EXPIRED' => 'cancelled',
                        default => null,
                    };
                    if ($mapped) {
                        $service->updateSubscriptionStatus($subscriptionId, $mapped);
                    }
                }
            }
        }

        // Always 200 once the signature checks out — Cashfree retries on
        // non-2xx, and we don't want retries for event types we ignore.
        return $this->response->setStatusCode(200)->setBody('ok');
    }
}
