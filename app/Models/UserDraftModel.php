<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Model;

class UserDraftModel extends Model
{
    protected $table            = 'user_drafts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'id', 'created_by_user_id', 'name', 'role', 'payload',
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
     * A New User draft is a personal, in-progress workspace, not
     * hierarchy-visible data like an enrolment draft — only the admin who
     * started it (or someone with unrestricted access) can see or resume
     * it.
     */
    public function scopedTo(User $actor)
    {
        if ($actor->hasGlobalAccess()) {
            return $this;
        }

        return $this->where('created_by_user_id', $actor->id);
    }
}
