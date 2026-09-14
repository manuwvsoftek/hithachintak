<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrects the prior migration's Dev Admin defaults: Dev Admin is a
 * strict superset of Super Admin (Full on every module Super Admin has,
 * plus Full on Settings & Integrations), not a role restricted to only
 * Settings & Integrations as SeparateDevAdminPermissions (000017) first
 * set it up. Same reasoning as that migration for why this has to be a
 * migration and not just an updated seeder: RolePermissionSeeder can't
 * correct a role_permissions row this database already has.
 */
class DevAdminFullAccess extends Migration
{
    private const MODULES_FULL_FOR_DEV_ADMIN = [
        'Dashboard', 'Masters', 'Users & Hierarchy', 'Enrolments', 'Reports',
    ];

    public function up(): void
    {
        foreach (self::MODULES_FULL_FOR_DEV_ADMIN as $module) {
            $this->upsert('dev_admin', $module, 'Full');
        }
    }

    public function down(): void
    {
        foreach (self::MODULES_FULL_FOR_DEV_ADMIN as $module) {
            $this->upsert('dev_admin', $module, 'None');
        }
    }

    private function upsert(string $role, string $module, string $level): void
    {
        $builder  = $this->db->table('role_permissions');
        $existing = $builder->where('role', $role)->where('module', $module)->get()->getRowArray();

        if ($existing) {
            $builder->where('id', $existing['id'])->update(['level' => $level]);
        } else {
            $builder->insert(['role' => $role, 'module' => $module, 'level' => $level]);
        }
    }
}
