<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** A Prakhand Admin's assigned Prakhands (within their one fixed Prant + Jila) when they have more than one. Empty = falls back to users.prakhand_id; users.scope_all = all Prakhands in that Jila. */
class UserPrakhandScopeModel extends Model
{
    protected $table         = 'user_prakhand_scopes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'prakhand_id', 'created_at'];

    public function idsFor(int $userId): array
    {
        return array_column($this->where('user_id', $userId)->findAll(), 'prakhand_id');
    }

    public function replaceFor(int $userId, array $prakhandIds): void
    {
        $this->where('user_id', $userId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $prakhandIds)) as $prakhandId) {
            $this->insert(['user_id' => $userId, 'prakhand_id' => $prakhandId, 'created_at' => $now]);
        }
    }
}
