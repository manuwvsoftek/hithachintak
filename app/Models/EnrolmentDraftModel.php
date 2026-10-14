<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\User;
use App\Libraries\Rbac\LocationScope;
use CodeIgniter\Model;

class EnrolmentDraftModel extends Model
{
    protected $table          = 'enrolment_drafts';
    protected $primaryKey     = 'id';
    protected $useAutoIncrement = false;
    protected $returnType     = 'array';
    protected $useTimestamps  = true;

    protected $allowedFields = [
        'id', 'created_by_user_id', 'prant_id', 'jila_id', 'prakhand_id', 'programme_code', 'member_name', 'payload',
    ];

    protected $validationRules = [
        'id'                 => 'required|max_length[36]',
        'created_by_user_id' => 'required|is_natural_no_zero',
        'payload'            => 'required',
    ];

     /**
     * Opportunistic cleanup: shared hosting here has no guaranteed cron
     * worker, so expired drafts are swept whenever the Pending Drafts list
     * is next loaded, rather than relying on a scheduled job. A draft
     * nobody has touched (autosave or otherwise) in 5 days is considered
     * abandoned.
     */
    public function pruneExpired(): void
    {
        $this->where('updated_at <', date('Y-m-d H:i:s', strtotime('-5 days')))->delete();
    }

    /**
     * Same tiered visibility as EnrolmentModel::scopedTo(), applied to a
     * draft's own location columns — a Karyakarta only ever sees drafts
     * they started themselves, since a draft has no collected_by concept
     * of its own yet (it isn't a real enrolment).
     */
    public function scopedTo(User $actor)
    {
        $builder = $this;

        if ($actor->hasGlobalAccess()) {
            return $builder;
        }

       if (in_array($actor->role, [User::ROLE_PRANTA_ADMIN, User::ROLE_KENDREEYA_ADMIN], true)) {
            $ids = LocationScope::prantIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolment_drafts.prant_id', $ids);
        }

       if ($actor->role === User::ROLE_SUB_ADMIN) {
            $ids = LocationScope::jilaIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolment_drafts.jila_id', $ids);
        }

        if ($actor->role === User::ROLE_PRAKHAND_ADMIN) {
            $ids = LocationScope::prakhandIds($actor);

            return $ids === null ? $builder : $builder->whereIn('enrolment_drafts.prakhand_id', $ids);
        }

        return $builder->where('enrolment_drafts.created_by_user_id', $actor->id);
    }
}
