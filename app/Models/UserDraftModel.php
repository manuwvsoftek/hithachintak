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
