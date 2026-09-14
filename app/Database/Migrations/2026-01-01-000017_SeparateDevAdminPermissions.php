<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Splits Settings & Integrations access out of Super Admin and into a
 * new Dev Admin role. RolePermissionSeeder already has the right
 * *defaults* for a fresh install, but it only inserts rows that don't
 * already exist — it can never correct a role_permissions row this
 * database already seeded with the old (Super Admin = Full on
 * Settings & Integrations) default. This migration is the one-time,
 * idempotent correction that has to actually run against an existing
 * database, not just a fresh one — data, not schema, but it needs the
 * same "runs exactly once as part of the deploy" guarantee migrations
 * give and a seeder re-run alone would not.
 *
 * The one Dev Admin account itself is provisioned separately by
 * DevAdminSeeder (`php spark db:seed DevAdminSeeder`), the same way
 * DevSuperAdminSeeder provisions the first Super Admin — not by this
 * migration, which only touches the permission matrix.
 */
class SeparateDevAdminPermissions extends Migration
{
    private const MODULES_NONE_FOR_DEV_ADMIN = [
        'Dashboard', 'Masters', 'Users & Hierarchy', 'Enrolments', 'Reports',
    ];

    public function up(): void
    {
        $this->upsert('super_admin', 'Settings & Integrations', 'None');
        $this->upsert('dev_admin', 'Settings & Integrations', 'Full');

        foreach (self::MODULES_NONE_FOR_DEV_ADMIN as $module) {
            $this->upsert('dev_admin', $module, 'None');
        }
    }

    public function down(): void
    {
        $this->upsert('super_admin', 'Settings & Integrations', 'Full');
        $this->db->table('role_permissions')->where('role', 'dev_admin')->delete();
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
