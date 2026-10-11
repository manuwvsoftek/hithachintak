<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** A Pranta Admin's assigned Prants when they have more than one (see CreateUserLocationScopes). Empty = falls back to users.prant_id; users.scope_all = all Prants. */
class UserPrantScopeModel extends Model
{
    protected $table         = 'user_prant_scopes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'prant_id', 'created_at'];

    /**
     * Cast to int: MySQLi returns every column as a string unless
     * numberNative is on (it isn't — see Config\Database), and callers
     * (LocationScope, the Edit User view's in_array(..., true) checks)
     * rely on this returning real ints, not numeric strings.
     *
     * @return list<int>
     */
    public function idsFor(int $userId): array
    {
        return array_map('intval', array_column($this->where('user_id', $userId)->findAll(), 'prant_id'));
    }

    public function replaceFor(int $userId, array $prantIds): void
    {
        $this->where('user_id', $userId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $prantIds)) as $prantId) {
            $this->insert(['user_id' => $userId, 'prant_id' => $prantId, 'created_at' => $now]);
        }
    }
}
