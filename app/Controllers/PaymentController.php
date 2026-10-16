<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Cashfree\CashfreeClient;
use App\Libraries\Enrolment\EnrolmentService;
use App\Libraries\Razorpay\RazorpayClient;
use App\Models\AutopaySubscriptionModel;

/** Receives payment-gateway webhooks — one endpoint per Prant per provider. */
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
                $service->activateAutopaySubscription('cashfree', $subscriptionId);
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
                $model   = new AutopaySubscriptionModel();
                $sub     = $model->forGatewaySubscriptionId('cashfree', $subscriptionId);

                if ($sub) {
                    $service->recordAutopayCharge('cashfree', $subscriptionId, (string) $paymentId, (float) ($amount ?? $sub->amount), $success);
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
                    $service->activateAutopaySubscription('cashfree', $subscriptionId);
                } else {
                    $mapped = match ($status) {
                        'ON_HOLD', 'CUSTOMER_PAUSED' => 'paused',
                        'BANK_APPROVAL_PENDING'       => 'pending',
                        'COMPLETED', 'CUSTOMER_CANCELLED', 'EXPIRED', 'LINK_EXPIRED', 'CANCELLED', 'CARD_EXPIRED' => 'cancelled',
                        default => null,
                    };
                    if ($mapped) {
                        $service->updateSubscriptionStatus('cashfree', $subscriptionId, $mapped);
                    }
                }
            }
        }

        // Always 200 once the signature checks out — Cashfree retries on
        // non-2xx, and we don't want retries for event types we ignore.
        return $this->response->setStatusCode(200)->setBody('ok');
    }

    /**
     * Razorpay webhooks, confirmed against Razorpay's own events
     * reference. Two structural differences from Cashfree's handler
     * above, both because Razorpay assigns its own order/payment ids
     * rather than letting us choose them:
     *  - Routing a regular payment vs. a Hithachintak cash remittance
     *    can't use an order-id string prefix (REMIT- vs HTC-) the way
     *    Cashfree's can — it's done by the matched enrolment's own
     *    status instead (cash_collected = a remittance in progress).
     *  - "SUBSCRIPTION_AUTH_STATUS"'s small-refunded-token-charge step
     *    has no Razorpay equivalent; subscription.activated fires once
     *    the mandate authenticates, subscription.charged fires for
     *    every real billing cycle including the first.
     */
    public function razorpayWebhook($prantId)
    {
        $prantId = (int) $prantId;

        $rawBody   = $this->request->getBody();
        $signature = $this->request->getHeaderLine('x-razorpay-signature');

        if (! $signature || ! (new RazorpayClient())->verifyWebhookSignature($prantId, $rawBody, $signature)) {
            log_message('warning', 'Razorpay webhook rejected: bad or missing signature for Prant {id}.', ['id' => $prantId]);

            return $this->response->setStatusCode(400)->setBody('invalid signature');
        }

        $payload = json_decode($rawBody, true) ?? [];
        $event   = $payload['event'] ?? '';
        $service = new EnrolmentService();

        if ($event === 'payment.captured') {
            $payment   = $payload['payload']['payment']['entity'] ?? [];
            $orderId   = $payment['order_id'] ?? null;
            $paymentId = $payment['id'] ?? null;

            if ($orderId && $paymentId) {
                $enrolmentModel = new \App\Models\EnrolmentModel();
                $enrolment      = $enrolmentModel->where('razorpay_order_id', $orderId)->first();

                if ($enrolment && $enrolment->status === \App\Entities\Enrolment::STATUS_CASH_COLLECTED) {
                    $service->confirmRazorpayCashRemittance($orderId, (string) $paymentId);
                } elseif ($enrolment) {
                    $service->markRazorpayPaid($orderId, (string) $paymentId);
                }
            }
        }

        if ($event === 'subscription.authenticated' || $event === 'subscription.activated') {
            $subscriptionId = $payload['payload']['subscription']['entity']['id'] ?? null;

            if ($subscriptionId) {
                $service->activateAutopaySubscription('razorpay', $subscriptionId);
            }
        }

        if ($event === 'subscription.charged') {
            $subscriptionId = $payload['payload']['subscription']['entity']['id'] ?? null;
            $payment        = $payload['payload']['payment']['entity'] ?? [];
            $paymentId      = $payment['id'] ?? null;
            $amountPaise    = $payment['amount'] ?? null;

            if ($subscriptionId && $paymentId) {
                $model = new AutopaySubscriptionModel();
                $sub   = $model->forGatewaySubscriptionId('razorpay', $subscriptionId);

                if ($sub) {
                    $amount = $amountPaise !== null ? ((float) $amountPaise) / 100 : $sub->amount;
                    $service->recordAutopayCharge('razorpay', $subscriptionId, (string) $paymentId, $amount, true);
                }
            }
        }

        if (in_array($event, ['subscription.cancelled', 'subscription.paused', 'subscription.halted', 'subscription.completed'], true)) {
            $subscriptionId = $payload['payload']['subscription']['entity']['id'] ?? null;

            $mapped = match ($event) {
                'subscription.paused'                        => 'paused',
                'subscription.cancelled', 'subscription.completed' => 'cancelled',
                // "halted" is Razorpay's term for repeated charge failures
                // (e.g. an expired mandate) — functionally the same dead
                // end as Cashfree's CARD_EXPIRED/EXPIRED, so it maps the
                // same way.
                'subscription.halted'                        => 'cancelled',
                default                                       => null,
            };

            if ($subscriptionId && $mapped) {
                $service->updateSubscriptionStatus('razorpay', $subscriptionId, $mapped);
            }
        }

        // Always 200 once the signature checks out — Razorpay retries on
        // non-2xx, and we don't want retries for event types we ignore.
        return $this->response->setStatusCode(200)->setBody('ok');
    }
}
