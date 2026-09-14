<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\Enrolment;
use App\Entities\User;
use App\Libraries\Rbac\LocationScope;
use CodeIgniter\Model;

class EnrolmentModel extends Model
{
    protected $table         = 'enrolments';
    protected $primaryKey    = 'id';
    protected $returnType    = Enrolment::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'receipt_no', 'public_token', 'member_id', 'programme_id', 'prant_id', 'jila_id', 'prakhand_id',
        'collected_by_user_id', 'amount', 'language', 'payment_mode', 'status',
        'cashfree_order_id', 'cashfree_payment_id', 'cashfree_order_status', 'autopay_subscription_id',
        'otp_code_hash', 'otp_expires_at', 'otp_attempts', 'otp_verified_at',
        'paid_at', 'receipt_sent_whatsapp_at', 'receipt_sent_email_at', 'receipt_sent_sms_at',
    ];

    /**
     * Restricts a query to what the given actor's hierarchy level is
     * allowed to see: Super Admin/Dev Admin = all, Pranta Admin = their
     * Prant, Sub Admin = their Jila, Karyakarta = only their own
     * collections.
     */
    public function scopedTo(User $actor)
    {
        $builder = $this;

        if ($actor->hasGlobalAccess()) {
            return $builder;
        }

        // Column names qualified with the table — withDetails() joins
        // `users`, which (since the Prakhand Admin tier) also carries its
        // own prant_id/jila_id/prakhand_id columns, so an unqualified
        // where() here is ambiguous the moment both are in the query.
        // LocationScope handles the None-vs-set-vs-single distinction for
        // a role that's been assigned multiple (or "all") of its own
        // tier — null means unrestricted at that tier, skip the where().
        if ($actor->role === User::ROLE_PRANTA_ADMIN) {
            $ids = LocationScope::prantIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolments.prant_id', $ids);
        }

        if ($actor->role === User::ROLE_SUB_ADMIN) {
            $ids = LocationScope::jilaIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolments.jila_id', $ids);
        }

        if ($actor->role === User::ROLE_PRAKHAND_ADMIN) {
            $ids = LocationScope::prakhandIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolments.prakhand_id', $ids);
        }

        return $builder->where('enrolments.collected_by_user_id', $actor->id);
    }

    public function withDetails()
    {
        return $this->select('enrolments.*, members.name AS member_name, members.phone AS member_phone,
                members.email AS member_email, members.pan AS member_pan,
                programmes.name AS programme_name, programmes.code AS programme_code,
                prants.name AS prant_name, jilas.name AS jila_name, prakhands.name AS prakhand_name,
                users.name AS karyakarta_name')
            ->join('members', 'members.id = enrolments.member_id')
            ->join('programmes', 'programmes.id = enrolments.programme_id')
            ->join('prants', 'prants.id = enrolments.prant_id')
            ->join('jilas', 'jilas.id = enrolments.jila_id', 'left')
            ->join('prakhands', 'prakhands.id = enrolments.prakhand_id', 'left')
            ->join('users', 'users.id = enrolments.collected_by_user_id');
    }

    public function recentForDashboard(User $actor, int $limit = 5): array
    {
        return $this->scopedTo($actor)
            ->withDetails()
            ->orderBy('enrolments.created_at', 'DESC')
            ->limit($limit)
            ->findAll();
    }

    /**
     * Whether this phone number already has a live Hithachintak enrolment
     * (as the primary member — family members live in a separate table
     * and never trigger this check). A failed/cancelled attempt doesn't
     * count as "already registered", so the same number can try again.
     */
    public function hasActiveHithachintak(string $phone): bool
    {
        return $this->select('enrolments.id')
            ->join('members', 'members.id = enrolments.member_id')
            ->join('programmes', 'programmes.id = enrolments.programme_id')
            ->where('programmes.code', 'hc')
            ->where('members.phone', $phone)
            ->whereNotIn('enrolments.status', [Enrolment::STATUS_FAILED, Enrolment::STATUS_CANCELLED])
            ->countAllResults() > 0;
    }

    /**
     * Whether this phone number has a *receipted* Hithachintak enrolment —
     * used to gate every Admin/Karyakarta account into completing their
     * own Hithachintak self-enrolment before they can enrol anyone else.
     * Stricter than hasActiveHithachintak(): merely starting (OTP-pending,
     * awaiting payment) doesn't count as "done" here.
     */
    public function hasCompletedHithachintak(string $phone): bool
    {
        return $this->select('enrolments.id')
            ->join('members', 'members.id = enrolments.member_id')
            ->join('programmes', 'programmes.id = enrolments.programme_id')
            ->where('programmes.code', 'hc')
            ->where('members.phone', $phone)
            ->where("enrolments.receipt_no IS NOT NULL")
            ->countAllResults() > 0;
    }
}
