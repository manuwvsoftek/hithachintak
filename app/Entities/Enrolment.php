<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Enrolment extends Entity
{
    public const STATUS_OTP_PENDING        = 'otp_pending';
    public const STATUS_AWAITING_PAYMENT   = 'awaiting_payment';
    public const STATUS_PAID               = 'paid';
    public const STATUS_CASH_COLLECTED     = 'cash_collected';
    public const STATUS_CASH_PENDING_REMIT = 'cash_pending_remit';
    public const STATUS_REMITTED           = 'remitted';
    public const STATUS_FAILED             = 'failed';
    public const STATUS_CANCELLED          = 'cancelled';

    protected $casts = [
        'id'                   => 'integer',
        'member_id'            => 'integer',
        'programme_id'         => 'integer',
        'prant_id'             => 'integer',
        'jila_id'              => '?integer',
        'prakhand_id'          => '?integer',
        'collected_by_user_id' => 'integer',
        'amount'               => 'float',
        'otp_attempts'         => 'integer',
    ];

    public function isPaid(): bool
    {
        return in_array($this->attributes['status'], [self::STATUS_PAID, self::STATUS_REMITTED], true);
    }

    /**
     * A receipt exists once money has changed hands — paid online, cash
     * collected (even before remittance, for non-Hithachintak programmes),
     * or remitted (Hithachintak cash, receipt withheld until then).
     */
    public function hasReceipt(): bool
    {
        return ! empty($this->attributes['receipt_no']);
    }
}
