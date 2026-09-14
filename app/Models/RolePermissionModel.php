<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class RolePermissionModel extends Model
{
    protected $table         = 'role_permissions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['role', 'module', 'level'];

    public const LEVELS = ['None', 'Own', 'View', 'Edit', 'Full'];

    /** @return array<string, array<string, string>> module => role => level */
    public function matrix(): array
    {
        $rows   = $this->findAll();
        $matrix = [];
        foreach ($rows as $row) {
            $matrix[$row['module']][$row['role']] = $row['level'];
        }

        return $matrix;
    }

    public function levelFor(string $role, string $module): string
    {
        $row = $this->where('role', $role)->where('module', $module)->first();

        return $row['level'] ?? 'None';
    }

    public static function rank(string $level): int
    {
        $i = array_search($level, self::LEVELS, true);

        return $i === false ? 0 : $i;
    }

    public function atLeast(string $role, string $module, string $minLevel): bool
    {
        return self::rank($this->levelFor($role, $module)) >= self::rank($minLevel);
    }
}
