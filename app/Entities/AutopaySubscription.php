<?php

declare(strict_types=1);

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class AutopaySubscription extends Entity
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_FAILED    = 'failed';

    protected $casts = [
        'id'             => 'integer',
        'enrolment_id'   => 'integer',
        'member_id'      => 'integer',
        'programme_id'   => 'integer',
        'prant_id'       => 'integer',
        'amount'         => 'float',
    ];

    public function isActive(): bool
    {
        return $this->attributes['status'] === self::STATUS_ACTIVE;
    }
}
