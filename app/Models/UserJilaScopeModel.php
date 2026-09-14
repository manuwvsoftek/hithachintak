<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

/** A Jila Admin's assigned Jilas (within their one fixed Prant) when they have more than one. Empty = falls back to users.jila_id; users.scope_all = all Jilas in that Prant. */
class UserJilaScopeModel extends Model
{
    protected $table         = 'user_jila_scopes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['user_id', 'jila_id', 'created_at'];

    public function idsFor(int $userId): array
    {
        return array_column($this->where('user_id', $userId)->findAll(), 'jila_id');
    }

    public function replaceFor(int $userId, array $jilaIds): void
    {
        $this->where('user_id', $userId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_map('intval', $jilaIds)) as $jilaId) {
            $this->insert(['user_id' => $userId, 'jila_id' => $jilaId, 'created_at' => $now]);
        }
    }
}
