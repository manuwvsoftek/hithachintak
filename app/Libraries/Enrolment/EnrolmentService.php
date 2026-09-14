<?php

declare(strict_types=1);

namespace App\Libraries\Enrolment;

use App\Entities\AutopaySubscription;
use App\Entities\Enrolment;
use App\Entities\User;
use App\Libraries\Cashfree\CashfreeClient;
use App\Libraries\Msg91\Msg91Sms;
use App\Libraries\Msg91\Msg91WhatsApp;
use App\Libraries\Notify\EmailSender;
use App\Models\AutopaySubscriptionModel;
use App\Models\CashRemittanceModel;
use App\Models\EnrolmentFamilyMemberModel;
use App\Models\EnrolmentModel;
use App\Models\MemberModel;
use App\Models\ProgrammeModel;
use App\Models\UserModel;

/**
 * Drives an enrolment through form -> OTP verification -> payment (UPI/QR
 * via Cashfree, or cash) -> receipt. Shared by the Admin "New Enrolment"
 * panel and the Karyakarta field app — both post through the same
 * EnrolmentController actions.
 */
class EnrolmentService
{
    private const OTP_TTL_SECONDS = 300;
    private const OTP_MAX_ATTEMPTS = 5;
    private const OTP_RESEND_COOLDOWN = 30;

    /** Hithachintak charges per person (head of family + each additional member), capped at this many additional members. */
    private const HITHACHINTAK_MAX_FAMILY_MEMBERS = 9;

    /** Hard ceiling on the Autopay Monthly Donation's recurring amount, regardless of what the donor requests. */
    private const AUTOPAY_MAX_AMOUNT = 5000.0;

    public function __construct(
        private readonly EnrolmentModel $enrolments = new EnrolmentModel(),
        private readonly EnrolmentFamilyMemberModel $familyMembers = new EnrolmentFamilyMemberModel(),
        private readonly MemberModel $members = new MemberModel(),
        private readonly ProgrammeModel $programmes = new ProgrammeModel(),
        private readonly CashRemittanceModel $remittances = new CashRemittanceModel(),
        private readonly AutopaySubscriptionModel $subscriptions = new AutopaySubscriptionModel(),
        private readonly Msg91Sms $sms = new Msg91Sms(),
        private readonly Msg91WhatsApp $whatsapp = new Msg91WhatsApp(),
        private readonly EmailSender $email = new EmailSender(),
        private readonly CashfreeClient $cashfree = new CashfreeClient(),
    ) {
        helper('app');
    }

    /**
     * Creates the member + enrolment row and sends the first OTP — but only
     * when $data['payment_mode'] is 'cash'. Cash is collected in person
     * with no gateway step of its own to vouch for the member, so the OTP
     * is what verifies them before the Karyakarta banks the cash; an
     * online payment has the member acting on their own device/UPI PIN at
     * the gateway, which is verification enough on its own, so that path
     * skips straight to STATUS_AWAITING_PAYMENT with no OTP at all. $data
     * keys: programme_code, prant_id, jila_id, prakhand_id, language,
     * payment_mode ('online'|'cash'), member_name, member_phone,
     * age_or_dob, profession, address, pincode, amount, family_members
     * (list of ['first_name'=>..,'last_name'=>..,'age_or_dob'=>..,'profession'=>..,'contact_number'=>..],
     * first_name+age_or_dob required per row, the rest optional).
     */
    public function start(array $data, User $collector): Enrolment
    {
        $programme = $this->programmes->byCode($data['programme_code']);
        if (! $programme) {
            throw new \InvalidArgumentException('Unknown programme.');
        }

        // First name and Age are mandatory per family member; Last name,
        // Profession and Contact number are optional. A row missing either
        // required field is dropped rather than saved half-filled, and —
        // for Hithachintak, where this list also drives the amount below —
        // never trust the client to have already capped it to the allowed
        // maximum.
        $familyMembers = [];
        foreach ($data['family_members'] ?? [] as $fm) {
            $firstName = trim($fm['first_name'] ?? '');
            if ($firstName === '' || trim($fm['age_or_dob'] ?? '') === '') {
                continue;
            }
            $fm['first_name'] = $firstName;
            $fm['name']       = trim($firstName . ' ' . trim($fm['last_name'] ?? ''));
            $familyMembers[]  = $fm;
            if ($programme['code'] === 'hc' && count($familyMembers) >= self::HITHACHINTAK_MAX_FAMILY_MEMBERS) {
                break;
            }
        }

        if ($programme['code'] === 'hc') {
            // ₹{rate} per person — the head of family plus each additional
            // member — never the amount the client happened to submit.
            $amount = (float) $programme['rate'] * (1 + count($familyMembers));
        } elseif ($programme['is_recurring']) {
            $amount = min(max((float) $data['amount'], (float) $programme['rate']), self::AUTOPAY_MAX_AMOUNT);
        } elseif ($programme['mode'] === 'min') {
            $amount = max((float) $data['amount'], (float) $programme['rate']);
        } else {
            $amount = (float) $data['amount'];
        }

        // A recurring (Autopay) mandate is always set up online at the
        // gateway — there's no such thing as cash-collecting a monthly
        // mandate — so it never needs the OTP path regardless of what was
        // submitted.
        $cashFlow = ($data['payment_mode'] ?? 'online') === 'cash' && ! $programme['is_recurring'];

        $memberFirstName = trim($data['member_first_name']);
        $memberLastName  = trim($data['member_last_name'] ?? '');
        $memberFullName  = trim($memberFirstName . ' ' . $memberLastName);

        $member = $this->members->findOrCreate([
            'phone'          => $data['member_phone'],
            'email'          => $data['email'] ?? null,
            'name'           => $memberFullName,
            'first_name'     => $memberFirstName,
            'last_name'      => $memberLastName ?: null,
            'age_or_dob'     => $data['age_or_dob'] ?? null,
            'profession'     => $data['profession'] ?? null,
            'address'        => $data['address'] ?? null,
            'pincode'        => $data['pincode'] ?? null,
            'pan'            => $data['pan'] ?? null,
            'preferred_lang' => $data['language'] ?? 'en',
        ]);

        $enrolmentId = $this->enrolments->insert([
            'member_id'            => $member['id'],
            'programme_id'         => $programme['id'],
            'prant_id'             => $data['prant_id'],
            'jila_id'              => $data['jila_id'] ?? null,
            'prakhand_id'          => $data['prakhand_id'] ?? null,
            'collected_by_user_id' => $collector->id,
            'amount'               => $amount,
            'language'             => $data['language'] ?? 'en',
            'status'               => $cashFlow ? Enrolment::STATUS_OTP_PENDING : Enrolment::STATUS_AWAITING_PAYMENT,
        ], true);

        foreach ($familyMembers as $fm) {
            $this->familyMembers->insert([
                'enrolment_id'   => $enrolmentId,
                'name'           => $fm['name'],
                'first_name'     => $fm['first_name'],
                'last_name'      => trim($fm['last_name'] ?? '') ?: null,
                'age_or_dob'     => $fm['age_or_dob'],
                'profession'     => $fm['profession'] ?? null,
                'contact_number' => $fm['contact_number'] ?? null,
            ]);
        }

        if ($cashFlow) {
            $this->issueOtp($this->enrolments->find($enrolmentId));
        }

        return $this->enrolments->find($enrolmentId);
    }

    /**
     * (Re)issues an OTP for the enrolment's member phone. Enforces a resend
     * cooldown to limit SMS spend.
     *
     * @throws \RuntimeException if called again before the cooldown elapses
     */
    public function issueOtp(Enrolment $enrolment): void
    {
        if ($enrolment->otp_expires_at
            && strtotime($enrolment->otp_expires_at) - self::OTP_TTL_SECONDS > time() - self::OTP_RESEND_COOLDOWN
        ) {
            throw new \RuntimeException('Please wait before requesting another OTP.');
        }

        $code = (string) random_int(100000, 999999);

        $this->enrolments->update($enrolment->id, [
            'otp_code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'otp_expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS),
            'otp_attempts'   => 0,
        ]);

        $member = $this->members->find($enrolment->member_id);
        $this->sms->sendOtp($member['phone'], $code);
    }

    public function verifyOtp(Enrolment $enrolment, string $submittedCode): bool
    {
        if (! $enrolment->otp_code_hash || strtotime($enrolment->otp_expires_at) < time()) {
            return false;
        }
        if ($enrolment->otp_attempts >= self::OTP_MAX_ATTEMPTS) {
            return false;
        }
        if (! password_verify($submittedCode, $enrolment->otp_code_hash)) {
            $this->enrolments->update($enrolment->id, ['otp_attempts' => $enrolment->otp_attempts + 1]);

            return false;
        }

        $this->enrolments->update($enrolment->id, [
            'otp_verified_at' => date('Y-m-d H:i:s'),
            'status'          => Enrolment::STATUS_AWAITING_PAYMENT,
        ]);

        return true;
    }

    /**
     * Creates a Cashfree order for UPI/QR payment. Returns the Cashfree
     * order response (incl. payment_session_id for the Checkout JS SDK),
     * or null if the Prant's gateway isn't configured yet.
     */
    public function createCashfreeOrder(Enrolment $enrolment, string $returnUrl, string $notifyUrl): ?array
    {
        $member    = $this->members->find($enrolment->member_id);
        $orderId   = 'HTC-' . $enrolment->id . '-' . bin2hex(random_bytes(3));

        $order = $this->cashfree->createOrder(
            $enrolment->prant_id,
            $orderId,
            $enrolment->amount,
            [
                'customer_id'    => (string) $member['id'],
                'customer_phone' => $member['phone'],
                'customer_name'  => $member['name'],
            ],
            $returnUrl,
            $notifyUrl,
            'Hithachintak Abhiyan enrolment #' . $enrolment->id
        );

        if (! $order) {
            return null;
        }

        $this->enrolments->update($enrolment->id, [
            'payment_mode'          => 'upi',
            'cashfree_order_id'     => $orderId,
            'cashfree_order_status' => $order['order_status'] ?? 'ACTIVE',
        ]);

        return $order;
    }

    /** Called from the Cashfree webhook once a payment succeeds. */
    public function markCashfreePaid(string $cashfreeOrderId, string $paymentId): void
    {
        $enrolment = $this->enrolments->where('cashfree_order_id', $cashfreeOrderId)->first();
        if (! $enrolment || $enrolment->isPaid()) {
            return;
        }

        $this->enrolments->update($enrolment->id, [
            'cashfree_payment_id'   => $paymentId,
            'cashfree_order_status' => 'PAID',
            'status'                => Enrolment::STATUS_PAID,
            'paid_at'               => date('Y-m-d H:i:s'),
        ]);

        $enrolment = $this->enrolments->find($enrolment->id);
        $this->finalizeReceipt($enrolment);
    }

    /**
     * Sets up a Cashfree Autopay mandate for a recurring-donation enrolment
     * (Autopay Monthly Donation to VHP). Returns the raw Cashfree
     * subscription-create response (used by the checkout view to launch
     * the donor's mandate-authorization step), or null if the Prant's
     * gateway isn't configured yet.
     */
    public function createAutopaySubscription(Enrolment $enrolment, string $returnUrl): ?array
    {
        $member         = $this->members->find($enrolment->member_id);
        $subscriptionId = 'AMD-SUB-' . $enrolment->id . '-' . bin2hex(random_bytes(3));

        $subscription = $this->cashfree->createSubscription(
            $enrolment->prant_id,
            $subscriptionId,
            $enrolment->amount,
            [
                'customer_phone' => $member['phone'],
                'customer_name'  => $member['name'],
                'customer_email' => $member['email'] ?? null,
            ],
            $returnUrl,
            'VHP Autopay Monthly Donation — enrolment #' . $enrolment->id
        );

        if (! $subscription) {
            return null;
        }

        $subscriptionRowId = $this->subscriptions->insert([
            'enrolment_id'             => $enrolment->id,
            'member_id'                => $enrolment->member_id,
            'programme_id'             => $enrolment->programme_id,
            'prant_id'                 => $enrolment->prant_id,
            'cashfree_subscription_id' => $subscriptionId,
            'amount'                   => $enrolment->amount,
            'status'                   => AutopaySubscription::STATUS_PENDING,
        ], true);

        $this->enrolments->update($enrolment->id, [
            'payment_mode'             => 'autopay',
            'autopay_subscription_id'  => $subscriptionRowId,
        ]);

        return $subscription;
    }

    /**
     * Called once a donor has authorized the mandate (from the return-URL
     * poll or the subscription-status webhook, whichever fires first —
     * idempotent either way). This only flips the mandate's own status —
     * Cashfree's authorization step is a small refunded token charge, not
     * the donor's real monthly amount, so no receipt is issued here. The
     * first real ₹{amount} donation, like every one after it, arrives as
     * its own SUBSCRIPTION_PAYMENT_SUCCESS_WEBHOOK and is handled
     * uniformly by recordAutopayCharge().
     */
    public function activateAutopaySubscription(string $cashfreeSubscriptionId): void
    {
        $subscription = $this->subscriptions->forCashfreeSubscriptionId($cashfreeSubscriptionId);
        if (! $subscription || $subscription->isActive()) {
            return;
        }

        $this->subscriptions->update($subscription->id, [
            'status'        => AutopaySubscription::STATUS_ACTIVE,
            'authorized_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Called from the Cashfree webhook for a subscription lifecycle change
     * that isn't a payment (cancelled/paused by the donor's bank or by
     * Cashfree). Authorization and payment-carrying transitions go
     * through activateAutopaySubscription()/recordAutopayCharge() instead.
     */
    public function updateSubscriptionStatus(string $cashfreeSubscriptionId, string $status): void
    {
        $subscription = $this->subscriptions->forCashfreeSubscriptionId($cashfreeSubscriptionId);
        if (! $subscription) {
            return;
        }

        $data = ['status' => $status];
        if ($status === AutopaySubscription::STATUS_CANCELLED) {
            $data['cancelled_at'] = date('Y-m-d H:i:s');
        }
        $this->subscriptions->update($subscription->id, $data);
    }

    /**
     * Records one successful (or failed) monthly charge under a
     * subscription — this fires for every real debit, the first month
     * included, since authorization itself doesn't move the donor's real
     * money (see activateAutopaySubscription()). The first successful
     * charge reuses the founding enrolment from the wizard (marks it
     * paid); every one after that becomes its own `enrolments` row — same
     * member/programme/prant/collector as the founding enrolment — so it
     * gets a receipt number, PDF and SMS/WhatsApp notification through
     * the exact same pipeline as any other payment, and shows up in the
     * Enrolments/Collections/Reports screens for free.
     */
    public function recordAutopayCharge(string $cashfreeSubscriptionId, string $paymentId, float $amount, bool $success): void
    {
        $subscription = $this->subscriptions->forCashfreeSubscriptionId($cashfreeSubscriptionId);
        if (! $subscription) {
            log_message('warning', 'Autopay charge webhook for unknown subscription {id}.', ['id' => $cashfreeSubscriptionId]);

            return;
        }

        if (! $success) {
            log_message('warning', 'Autopay charge failed for subscription {id} (Cashfree payment {pid}).', [
                'id' => $cashfreeSubscriptionId, 'pid' => $paymentId,
            ]);

            return;
        }

        $now = date('Y-m-d H:i:s');
        $this->subscriptions->update($subscription->id, [
            // A payment webhook implies the mandate is authorized even if
            // the status-update webhook hasn't landed yet.
            'status'         => AutopaySubscription::STATUS_ACTIVE,
            'last_charge_at' => $now,
        ]);

        $founding = $this->enrolments->find($subscription->enrolment_id);

        if ($founding && ! $founding->isPaid()) {
            $this->enrolments->update($founding->id, [
                'payment_mode'        => 'autopay',
                'status'              => Enrolment::STATUS_PAID,
                'cashfree_payment_id' => $paymentId,
                'paid_at'             => $now,
            ]);
            $this->finalizeReceipt($this->enrolments->find($founding->id));

            return;
        }

        $chargeId = $this->enrolments->insert([
            'member_id'               => $subscription->member_id,
            'programme_id'            => $subscription->programme_id,
            'prant_id'                => $subscription->prant_id,
            'jila_id'                 => $founding->jila_id ?? null,
            'prakhand_id'             => $founding->prakhand_id ?? null,
            'collected_by_user_id'    => $founding->collected_by_user_id,
            'amount'                  => $amount,
            'language'                => $founding->language ?? 'en',
            'payment_mode'            => 'autopay',
            'status'                  => Enrolment::STATUS_PAID,
            'cashfree_payment_id'     => $paymentId,
            'paid_at'                 => $now,
            'autopay_subscription_id' => $subscription->id,
        ], true);

        $this->finalizeReceipt($this->enrolments->find($chargeId));
    }

    /**
     * Marks cash collected. Hithachintak requires the collecting user to
     * remit it onward instantly via a real Cashfree payment before a
     * receipt is issued (see createRemittanceOrder()/confirmCashRemittance());
     * every other programme gets its receipt immediately, with
     * reconciliation tracked separately (and manually) via Collections.
     */
    public function markCashCollected(Enrolment $enrolment): Enrolment
    {
        $programme = $this->programmes->find($enrolment->programme_id);
        $requiresInstantRemit = $programme['code'] === 'hc';

        $this->enrolments->update($enrolment->id, [
            'payment_mode' => 'cash',
            'status'       => $requiresInstantRemit ? Enrolment::STATUS_CASH_COLLECTED : Enrolment::STATUS_CASH_PENDING_REMIT,
        ]);

        $this->remittances->insert([
            'user_id'      => $enrolment->collected_by_user_id,
            'enrolment_id' => $enrolment->id,
            'amount'       => $enrolment->amount,
            'status'       => 'due',
        ]);

        $enrolment = $this->enrolments->find($enrolment->id);

        if (! $requiresInstantRemit) {
            $this->finalizeReceipt($enrolment);

            return $this->enrolments->find($enrolment->id);
        }

        return $enrolment;
    }

    /**
     * Creates the Cashfree order the collecting Karyakarta pays, on their
     * own UPI app, to remit Hithachintak cash to the Trust the instant
     * it's collected. Mirrors createCashfreeOrder() but the customer is
     * the collecting Karyakarta, not the donor, and the order id is
     * prefixed distinctly (REMIT- vs HTC-) so the webhook can route each
     * to the right EnrolmentService method. Only valid while the
     * enrolment is sitting in cash_collected, awaiting this remittance.
     */
    public function createRemittanceOrder(Enrolment $enrolment, string $returnUrl, string $notifyUrl): ?array
    {
        if ($enrolment->status !== Enrolment::STATUS_CASH_COLLECTED) {
            return null;
        }

        $collector = (new UserModel())->find($enrolment->collected_by_user_id);
        $orderId   = 'REMIT-' . $enrolment->id . '-' . bin2hex(random_bytes(3));

        $order = $this->cashfree->createOrder(
            $enrolment->prant_id,
            $orderId,
            $enrolment->amount,
            [
                'customer_id'    => (string) $collector->id,
                'customer_phone' => $collector->phone,
                'customer_name'  => $collector->name,
            ],
            $returnUrl,
            $notifyUrl,
            'Hithachintak cash remittance — enrolment #' . $enrolment->id
        );

        if (! $order) {
            return null;
        }

        $this->enrolments->update($enrolment->id, [
            'cashfree_order_id'     => $orderId,
            'cashfree_order_status' => $order['order_status'] ?? 'ACTIVE',
        ]);

        return $order;
    }

    /**
     * Called once the Karyakarta's remittance order clears — from the
     * return-URL poll or the PAYMENT_SUCCESS_WEBHOOK, whichever fires
     * first (idempotent either way, guarded by isPaid()). Only now, with
     * a real gateway payment confirmed, does the cash enrolment become
     * remitted and get its receipt.
     */
    public function confirmCashRemittance(string $cashfreeOrderId, string $paymentId): void
    {
        $enrolment = $this->enrolments->where('cashfree_order_id', $cashfreeOrderId)->first();
        if (! $enrolment || $enrolment->isPaid()) {
            return;
        }

        $due = $this->remittances->where('enrolment_id', $enrolment->id)->where('status', 'due')->first();
        if ($due) {
            $this->remittances->update($due['id'], [
                'status'      => 'remitted',
                'upi_ref'     => $paymentId,
                'remitted_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->enrolments->update($enrolment->id, [
            'cashfree_payment_id'   => $paymentId,
            'cashfree_order_status' => 'PAID',
            'status'                => Enrolment::STATUS_REMITTED,
        ]);

        $enrolment = $this->enrolments->find($enrolment->id);
        $this->finalizeReceipt($enrolment);
    }

    private function finalizeReceipt(Enrolment $enrolment): void
    {
        if ($enrolment->receipt_no) {
            return;
        }

        $programme = $this->programmes->find($enrolment->programme_id);
        $receiptNo = $this->nextReceiptNumber($programme['code']);

        $this->enrolments->update($enrolment->id, [
            'receipt_no'   => $receiptNo,
            'public_token' => $this->generatePublicToken(),
        ]);

        $enrolment = $this->enrolments->find($enrolment->id);
        $this->sendReceiptNotifications($enrolment, ['sms']);
    }

    /**
     * A short random token for the public receipt link — deliberately not
     * derived from the id or receipt_no, so it can't be guessed/enumerated.
     * Collisions are astronomically unlikely (40 bits of randomness) but a
     * unique constraint backs this up, so retry on the rare clash.
     */
    private function generatePublicToken(): string
    {
        do {
            $token = strtoupper(bin2hex(random_bytes(5)));
        } while ($this->enrolments->where('public_token', $token)->first());

        return $token;
    }

    /**
     * Atomically allocates the next receipt number, e.g. HC/2026/000502.
     * Uses MySQL's upsert on the production driver; SQLite (dev/test only)
     * gets a portable transactional fallback since it has no equivalent.
     */
    public function nextReceiptNumber(string $programmeCode): string
    {
        $db   = db_connect();
        $year = (int) date('Y');

        $db->transStart();

        if ($db->getPlatform() === 'MySQLi') {
            $db->query('INSERT INTO receipt_sequences (programme_code, year, next_number) VALUES (?, ?, 2)
                         ON DUPLICATE KEY UPDATE next_number = next_number + 1', [$programmeCode, $year]);
            $row = $db->table('receipt_sequences')
                ->where('programme_code', $programmeCode)->where('year', $year)
                ->get()->getRow();
            $seq = $row ? (int) $row->next_number - 1 : 1;
        } else {
            $existing = $db->table('receipt_sequences')
                ->where('programme_code', $programmeCode)->where('year', $year)
                ->get()->getRow();
            if ($existing) {
                $db->table('receipt_sequences')->where('id', $existing->id)
                    ->set('next_number', 'next_number+1', false)->update();
                $seq = (int) $existing->next_number;
            } else {
                $db->table('receipt_sequences')->insert(['programme_code' => $programmeCode, 'year' => $year, 'next_number' => 2]);
                $seq = 1;
            }
        }

        $db->transComplete();

        return strtoupper($programmeCode) . '/' . $year . '/' . str_pad((string) $seq, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Sends the receipt over the requested channels. Defaults to SMS only
     * (called automatically on payment); WhatsApp/Email are opt-in from
     * the receipt screen's share buttons.
     *
     * @param list<'sms'|'whatsapp'|'email'> $channels
     */
    public function sendReceiptNotifications(Enrolment $enrolment, array $channels): array
    {
        $member = $this->members->find($enrolment->member_id);
        $sent   = [];
        $link   = base_url('h/rid=' . $enrolment->public_token);

        if (in_array('sms', $channels, true)) {
            $ok = $this->sms->sendReceiptNotification($member['phone'], $link);
            if ($ok) {
                $this->enrolments->update($enrolment->id, ['receipt_sent_sms_at' => date('Y-m-d H:i:s')]);
            }
            $sent['sms'] = $ok;
        }

        if (in_array('whatsapp', $channels, true)) {
            $ok = $this->whatsapp->sendReceipt($member['phone'], $link, $enrolment->language);
            if ($ok) {
                $this->enrolments->update($enrolment->id, ['receipt_sent_whatsapp_at' => date('Y-m-d H:i:s')]);
            }
            $sent['whatsapp'] = $ok;
        }

        if (in_array('email', $channels, true) && ! empty($member['email'])) {
            $programme = $this->programmes->find($enrolment->programme_id);
            $ok = $this->email->send(
                $member['email'],
                $member['name'],
                'Your Hithachintak Abhiyan receipt — ' . $enrolment->receipt_no,
                view('receipts/email', [
                    'enrolment'     => $enrolment,
                    'member'        => $member,
                    'programmeName' => $programme['name'] ?? '',
                    't'             => I18n::t($enrolment->language),
                    'trustName'     => I18n::trustName($enrolment->language),
                    'householdRows' => $this->familyMembers->householdRows($enrolment->id, $member),
                ])
            );
            if ($ok) {
                $this->enrolments->update($enrolment->id, ['receipt_sent_email_at' => date('Y-m-d H:i:s')]);
            }
            $sent['email'] = $ok;
        }

        return $sent;
    }
}
