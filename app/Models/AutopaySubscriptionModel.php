<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\AutopaySubscription;
use App\Entities\User;
use App\Libraries\Rbac\LocationScope;
use CodeIgniter\Model;

/**
 * One row per Autopay mandate (the "pledge"), created when a donor
 * authorizes the recurring debit. Each individual monthly charge is its
 * own row in `enrolments` (see AddAutopayDonations migration) — this
 * table just tracks the mandate's own lifecycle.
 */
class AutopaySubscriptionModel extends Model
{
    protected $table         = 'autopay_subscriptions';
    protected $primaryKey    = 'id';
    protected $returnType    = AutopaySubscription::class;
    protected $useTimestamps = true;

    protected $allowedFields = [
        'enrolment_id', 'member_id', 'programme_id', 'prant_id',
        'cashfree_subscription_id', 'amount', 'status',
        'authorized_at', 'last_charge_at', 'cancelled_at',
    ];

    public function forEnrolment(int $enrolmentId): ?AutopaySubscription
    {
        return $this->where('enrolment_id', $enrolmentId)->first();
    }

    public function forCashfreeSubscriptionId(string $subscriptionId): ?AutopaySubscription
    {
        return $this->where('cashfree_subscription_id', $subscriptionId)->first();
    }

    /**
     * Same hierarchy rule as EnrolmentModel::scopedTo(). Jila/Karyakarta
     * scoping needs enrolments.jila_id / collected_by_user_id, which don't
     * live on this table — reached via a subquery rather than a JOIN so
     * this composes cleanly with withDetails(), which joins enrolments
     * itself for display columns.
     */
    public function scopedTo(User $actor)
    {
        if ($actor->hasGlobalAccess()) {
            return $this;
        }

        if ($actor->role === User::ROLE_PRANTA_ADMIN) {
            $ids = LocationScope::prantIds($actor);

            return $ids === null ? $this : $this->whereIn('autopay_subscriptions.prant_id', $ids);
        }

        if ($actor->role === User::ROLE_SUB_ADMIN) {
            $ids = LocationScope::jilaIds($actor);

            if ($ids === null) {
                return $this;
            }

            return $this->whereIn('autopay_subscriptions.enrolment_id', static function ($q) use ($ids) {
                return $q->select('id')->from('enrolments')->whereIn('jila_id', $ids);
            });
        }

        if ($actor->role === User::ROLE_PRAKHAND_ADMIN) {
            $ids = LocationScope::prakhandIds($actor);

            if ($ids === null) {
                return $this;
            }

            return $this->whereIn('autopay_subscriptions.enrolment_id', static function ($q) use ($ids) {
                return $q->select('id')->from('enrolments')->whereIn('prakhand_id', $ids);
            });
        }

        return $this->whereIn('autopay_subscriptions.enrolment_id', static function ($q) use ($actor) {
            return $q->select('id')->from('enrolments')->where('collected_by_user_id', $actor->id);
        });
    }

    public function withDetails()
    {
        return $this->select('autopay_subscriptions.*, members.name AS member_name, members.phone AS member_phone,
                programmes.name AS programme_name, prants.name AS prant_name, users.name AS karyakarta_name')
            ->join('members', 'members.id = autopay_subscriptions.member_id')
            ->join('programmes', 'programmes.id = autopay_subscriptions.programme_id')
            ->join('prants', 'prants.id = autopay_subscriptions.prant_id')
            ->join('enrolments', 'enrolments.id = autopay_subscriptions.enrolment_id')
            ->join('users', 'users.id = enrolments.collected_by_user_id');
    }
}
