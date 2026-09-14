<?php

declare(strict_types=1);

namespace App\Models;

use App\Entities\User;
use App\Libraries\Rbac\LocationScope;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table          = 'users';
    protected $primaryKey     = 'id';
    protected $returnType     = User::class;
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;

    protected $allowedFields = [
        'name', 'phone', 'password_hash', 'password_plain', 'role',
        'prant_id', 'jila_id', 'prakhand_id', 'scope_all',
        'address', 'aadhar_number', 'email', 'profession',
        'status', 'must_reset_password',
        'last_login_at', 'created_by',
    ];

    protected $validationRules = [
        'name'  => 'required|min_length[2]|max_length[150]',
        'phone' => 'required|regex_match[/^[6-9][0-9]{9}$/]|is_unique[users.phone,id,{id}]',
        'role'  => 'required|in_list[dev_admin,super_admin,pranta_admin,sub_admin,prakhand_admin,karyakarta]',
        'email' => 'permit_empty|valid_email',
    ];

    protected $validationMessages = [
        'phone' => [
            'regex_match' => 'Enter a valid 10-digit Indian mobile number.',
            'is_unique'   => 'This phone number is already registered.',
        ],
    ];

    public function findByPhone(string $phone): ?User
    {
        return $this->where('phone', $phone)->first();
    }

    public function usersForRole(string $role): array
    {
        return $this->where('role', $role)->findAll();
    }

    /**
     * Users visible to the given actor, scoped by hierarchy:
     * Super Admin/Dev Admin see all; Pranta Admin sees their Prant; Jila
     * (Sub) Admin sees their Jila; Prakhand Admin sees their Prakhand;
     * Karyakarta sees only themself.
     *
     * The Dev Admin account itself is excluded from every actor's
     * results here, unconditionally — including when Dev Admin is the
     * actor. It's provisioned outside this whole hierarchy (see
     * DevAdminSeeder) and is never meant to appear in anyone's User
     * Directory, hierarchy counts, or role filter.
     */
    public function visibleTo(User $actor)
    {
        $builder = $this->where('users.role !=', User::ROLE_DEV_ADMIN);

        if ($actor->hasGlobalAccess()) {
            return $builder;
        }

        // Qualified with the table — the User Directory query joins
        // `prakhands` for its display name column, which carries its own
        // jila_id, so an unqualified where() here is ambiguous once that
        // join is present.
        if ($actor->role === User::ROLE_PRANTA_ADMIN) {
            $ids = LocationScope::prantIds($actor);

            return $ids === null ? $builder : $builder->whereIn('users.prant_id', $ids);
        }

        if ($actor->role === User::ROLE_SUB_ADMIN) {
            $ids = LocationScope::jilaIds($actor);

            return $ids === null ? $builder : $builder->whereIn('users.jila_id', $ids);
        }

        if ($actor->role === User::ROLE_PRAKHAND_ADMIN) {
            $ids = LocationScope::prakhandIds($actor);

            return $ids === null ? $builder : $builder->whereIn('users.prakhand_id', $ids);
        }

        return $builder->where('users.id', $actor->id);
    }
}
